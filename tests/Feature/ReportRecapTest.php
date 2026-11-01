<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportRecapTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('staffRoles')]
    public function test_recap_counts_all_statuses_and_reporters_with_consistent_summary_and_rows(UserRole $role): void
    {
        $area = Area::factory()->create(['name' => 'Wilayah A']);
        foreach (ReportStatus::cases() as $status) {
            Report::factory()->create(['area_id' => $area->id, 'status' => $status]);
        }
        $inactive = Area::factory()->create(['name' => 'Wilayah B', 'is_active' => false]);
        Report::factory()->count(2)->create(['area_id' => $inactive->id, 'status' => ReportStatus::Selesai]);
        Area::factory()->create(['name' => 'Wilayah C tanpa laporan']);
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('reports.recap', ['reporter_id' => 999999, 'status' => 'selesai']))
            ->assertOk()->assertViewHas('summary', ['total' => 8, 'active' => 5, 'completed' => 3])
            ->assertViewHas('rows', function ($rows): bool {
                $this->assertSame([[6, 5, 1], [2, 0, 2], [0, 0, 0]], $rows->map(fn (Area $area): array => [(int) $area->total_reports, (int) $area->active_reports, (int) $area->completed_reports])->all());
                foreach ($rows as $row) {
                    $this->assertSame($row->total_reports, $row->active_reports + $row->completed_reports);
                }

                return true;
            });
        $this->assertDatabaseCount('reports', 8);
        $this->assertDatabaseCount('report_status_logs', 0);
    }

    #[DataProvider('staffRoles')]
    public function test_area_filter_limits_both_summary_and_table_including_inactive_area(UserRole $role): void
    {
        $selected = Area::factory()->create(['is_active' => false]);
        Report::factory()->create(['area_id' => $selected->id]);
        Report::factory()->create(['area_id' => $selected->id, 'status' => ReportStatus::Selesai]);
        Report::factory()->count(3)->create();
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('reports.recap', ['area_id' => $selected->id]))
            ->assertOk()->assertViewHas('summary', ['total' => 2, 'active' => 1, 'completed' => 1])
            ->assertViewHas('rows', fn ($rows): bool => $rows->count() === 1 && $rows->first()->id === $selected->id)
            ->assertSee($selected->name)->assertSee('(nonaktif)');
    }

    public function test_empty_area_returns_zero_counts_and_empty_explanation(): void
    {
        $area = Area::factory()->create();
        Report::factory()->create();
        $this->actingAs(User::factory()->relawan()->create())->get(route('reports.recap', ['area_id' => $area->id]))
            ->assertOk()->assertViewHas('summary', ['total' => 0, 'active' => 0, 'completed' => 0])
            ->assertViewHas('rows', fn ($rows): bool => $rows->count() === 1 && $rows->first()->total_reports === 0)
            ->assertSee('Belum ada laporan di wilayah yang dipilih.');
    }

    public function test_no_data_and_blank_filter_show_zero_summary_without_error(): void
    {
        $this->actingAs(User::factory()->koordinator()->create())->get(route('reports.recap', ['area_id' => '']))
            ->assertOk()->assertViewHas('summary', ['total' => 0, 'active' => 0, 'completed' => 0])
            ->assertViewHas('rows', fn ($rows): bool => $rows->isEmpty())
            ->assertSee('Belum ada laporan untuk direkap.')->assertSee('Belum ada data wilayah.');
    }

    #[DataProvider('invalidAreas')]
    public function test_invalid_filter_is_rejected(mixed $area): void
    {
        $this->actingAs(User::factory()->relawan()->create())->getJson(route('reports.recap', ['area_id' => $area]))
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');
    }

    #[DataProvider('deniedUsers')]
    public function test_guests_reporters_and_inactive_staff_cannot_read_recap(?UserRole $role, bool $active, int $status): void
    {
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $this->getJson(route('reports.recap', ['area_id' => ['invalid'], 'role' => 'koordinator']))->assertStatus($status);
    }

    public function test_guest_html_redirects_to_login_and_writes_are_not_supported(): void
    {
        $this->get(route('reports.recap'))->assertRedirect(route('access'));
        $this->actingAs(User::factory()->koordinator()->create());
        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->{$method}(route('reports.recap'))->assertStatus(405);
        }
    }

    public function test_response_contains_no_individual_report_details_or_evidence_metadata(): void
    {
        $report = Report::factory()->create(['title' => 'Judul rahasia individu', 'description' => 'Catatan pribadi rahasia untuk simulasi.', 'evidence_path' => 'private/bukti-rahasia.pdf']);
        $before = $report->fresh()->toArray();
        $this->actingAs(User::factory()->relawan()->create())->get(route('reports.recap'))->assertOk()
            ->assertDontSee($report->title)->assertDontSee($report->description)->assertDontSee($report->evidence_path)
            ->assertDontSee($report->reporter->email)->assertDontSee($report->latitude);
        $this->assertEquals($before, $report->fresh()->toArray());
    }

    public static function staffRoles(): array
    {
        return [[UserRole::Relawan], [UserRole::Koordinator]];
    }

    public static function invalidAreas(): array
    {
        return [[['salah']], ['abc'], [999999], [0], [-1]];
    }

    public static function deniedUsers(): array
    {
        return [[null, true, 401], [UserRole::Pelapor, true, 403], [UserRole::Relawan, false, 401], [UserRole::Koordinator, false, 401]];
    }
}
