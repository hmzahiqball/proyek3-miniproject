<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoReportSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoReportSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_default_seeding_provides_all_roles_statuses_and_filtered_second_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('areas', 4);
        $this->assertDatabaseCount('reports', 26);
        foreach (ReportStatus::cases() as $status) {
            $this->assertGreaterThan(0, Report::query()->where('status', $status)->count());
        }
        $owner = User::query()->where('email', 'pelapor@example.test')->firstOrFail();
        $area = Area::query()->where('code', 'DEMO-A')->firstOrFail();
        $this->actingAs($owner)->get(route('pelapor.reports.index', ['q' => 'SIMULASI asap', 'area_id' => $area->id, 'status' => 'baru', 'page' => 2]))
            ->assertOk()->assertViewHas('reports', fn ($reports): bool => $reports->total() === 11 && $reports->count() === 1);
        $this->assertGreaterThan(0, Report::query()->whereHas('area', fn ($query) => $query->where('is_active', false))->count());
    }

    public function test_process_records_and_logs_are_consistent_with_each_demo_reports_status(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach (Report::query()->with(['reviews', 'forwarding', 'statusLogs.changedBy'])->get() as $report) {
            $previous = ReportStatus::Baru;
            $previousTime = $report->created_at;
            foreach ($report->statusLogs->sortBy('id') as $log) {
                $this->assertSame($previous, $log->from_status);
                $this->assertTrue($log->created_at->greaterThanOrEqualTo($previousTime));
                $expectedRole = $previous === ReportStatus::PerluPerbaikan ? UserRole::Pelapor
                    : ($log->to_status === ReportStatus::Diteruskan || $previous === ReportStatus::Diteruskan ? UserRole::Koordinator : UserRole::Relawan);
                $this->assertSame($expectedRole, $log->changedBy->role);
                $previous = $log->to_status;
                $previousTime = $log->created_at;
            }
            $this->assertSame($report->status, $previous);
            $this->assertTrue($report->updated_at->equalTo($previousTime));
            if ($report->status === ReportStatus::Baru) {
                $this->assertCount(0, $report->statusLogs);
            }
            if (in_array($report->status, [ReportStatus::PerluPerbaikan, ReportStatus::PerluDiteruskan], true)) {
                $this->assertSame($report->status->value, $report->reviews->last()->decision->value);
            }
            if ($report->forwarding) {
                $this->assertContains($report->status, [ReportStatus::Diteruskan, ReportStatus::Selesai]);
                $this->assertTrue($report->forwarding->forwarded_at->greaterThanOrEqualTo($report->created_at));
                $this->assertTrue($report->forwarding->forwarded_at->lessThanOrEqualTo(now()));
            } elseif ($report->status === ReportStatus::Diteruskan) {
                $this->fail('Laporan Diteruskan harus memiliki catatan penerusan.');
            }
            if ($report->status === ReportStatus::Selesai) {
                $this->assertNotNull($report->closed_at);
                $this->assertNotEmpty($report->closure_note);
                if (! $report->forwarding) {
                    $this->assertSame('selesai', $report->reviews->last()->decision->value);
                }
            } else {
                $this->assertNull($report->closed_at);
                $this->assertNull($report->closure_note);
            }
        }
    }

    public function test_evidence_is_private_valid_unique_and_matches_its_metadata(): void
    {
        $this->seed(DatabaseSeeder::class);
        $paths = [];
        $mimes = [];
        foreach (Report::query()->whereNotNull('evidence_path')->get() as $report) {
            $path = Storage::disk('local')->path($report->evidence_path);
            $this->assertFileExists($path);
            $this->assertSame($report->evidence_mime, mime_content_type($path));
            $this->assertSame($report->evidence_size, filesize($path));
            $this->assertLessThanOrEqual(2048 * 1024, $report->evidence_size);
            $paths[] = $report->evidence_path;
            $mimes[] = $report->evidence_mime;
        }
        $this->assertCount(count(array_unique($paths)), $paths);
        $this->assertEqualsCanonicalizing(['image/jpeg', 'image/png', 'application/pdf'], array_unique($mimes));
        $this->assertGreaterThan(0, Report::query()->whereNull('evidence_path')->count());
    }

    public function test_reseeding_preserves_existing_and_modified_demo_data_without_duplicate_rows_or_files(): void
    {
        $existing = Report::factory()->create(['title' => 'Laporan pengguna sebelum seeding']);
        $before = $existing->fresh()->toArray();
        $this->seed(DatabaseSeeder::class);
        $demo = Report::query()->where('title', 'like', '[DEMO17-%')->firstOrFail();
        $demo->title = 'Judul demo yang telah diperbaiki pengguna';
        $demo->description = 'Isi simulasi yang telah diubah oleh pengguna.';
        $demo->save();
        $modified = $demo->fresh()->toArray();
        $files = Storage::disk('local')->allFiles();
        $logCount = ReportStatusLog::query()->count();
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('reports', 27);
        $this->assertDatabaseCount('report_status_logs', $logCount);
        $this->assertEquals($before, $existing->fresh()->toArray());
        $this->assertEquals($modified, $demo->fresh()->toArray());
        $this->assertSame($files, Storage::disk('local')->allFiles());
    }

    public function test_failed_seed_rolls_back_batch_and_cleans_only_new_evidence(): void
    {
        $this->seed([AreaSeeder::class, DemoUserSeeder::class]);
        Storage::disk('local')->put('report-evidence/milik-pengguna.txt', 'Data pengguna tetap ada');
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into `report_reviews`')) {
                throw new RuntimeException('Kegagalan batch demo simulasi');
            }
        });
        try {
            $this->seed(DemoReportSeeder::class);
            $this->fail('Seeder seharusnya gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Kegagalan batch demo simulasi', $exception->getMessage());
        }
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('report_reviews', 0);
        $this->assertDatabaseCount('report_forwardings', 0);
        $this->assertDatabaseCount('report_status_logs', 0);
        $this->assertSame(['report-evidence/milik-pengguna.txt'], Storage::disk('local')->allFiles());
    }
}
