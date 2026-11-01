<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\ReportForwarding;
use App\Models\ReportStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReportStatusObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_content_evidence_and_unchanged_status_do_not_create_logs(): void
    {
        $report = Report::factory()->create();
        Report::factory()->create(['status' => ReportStatus::Selesai]);
        $report->title = 'Perubahan judul tanpa perubahan status';
        $report->evidence_path = 'report-evidence/simulasi.png';
        $report->save();
        $report->save();
        $report->status = ReportStatus::Baru;
        $report->save();
        $report->touch();
        $this->assertDatabaseCount('report_status_logs', 0);
    }

    public function test_system_updates_record_null_actor_and_each_actual_change_only_once(): void
    {
        $this->travelTo(now()->startOfSecond());
        $report = Report::factory()->create();
        DB::transaction(function () use ($report): void {
            $report->status = ReportStatus::Ditinjau;
            $report->save();
            $report->save();
            $report->status = ReportStatus::PerluPerbaikan;
            $report->save();
        });
        $logs = $report->statusLogs()->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(ReportStatus::Baru, $logs[0]->from_status);
        $this->assertSame(ReportStatus::Ditinjau, $logs[0]->to_status);
        $this->assertSame(ReportStatus::Ditinjau, $logs[1]->from_status);
        $this->assertSame(ReportStatus::PerluPerbaikan, $logs[1]->to_status);
        $this->assertNull($logs[0]->changed_by);
        $this->assertNull($logs[1]->changed_by);
        $this->assertTrue($logs[1]->created_at->equalTo(now()));
    }

    #[DataProvider('transitions')]
    public function test_every_business_transition_records_trusted_actor_statuses_and_server_time(string $route, ReportStatus $from, ReportStatus $to, UserRole $role): void
    {
        $this->travelTo(now()->startOfSecond());
        [$actor, $report] = $this->prepareTransition($route, $from, $role);
        $this->actingAs($actor)->post(route($route, $report), $this->payload($route, $to) + [
            'changed_by' => 999999, 'from_status' => 'selesai', 'to_status' => 'baru', 'created_at' => '2000-01-01',
        ])->assertRedirect();
        $log = $report->statusLogs()->sole();
        $this->assertSame($actor->id, $log->changed_by);
        $this->assertSame($from, $log->from_status);
        $this->assertSame($to, $log->to_status);
        $this->assertSame($to, $report->fresh()->status);
        $this->assertTrue($log->created_at->equalTo(now()));
        $this->assertDatabaseCount('report_status_logs', 1);
    }

    #[DataProvider('transitions')]
    public function test_failed_log_rolls_back_status_review_forwarding_and_closure(string $route, ReportStatus $from, ReportStatus $to, UserRole $role): void
    {
        [$actor, $report] = $this->prepareTransition($route, $from, $role);
        $before = $report->fresh()->toArray();
        $forwardingsBefore = ReportForwarding::query()->get()->toArray();
        Event::listen('eloquent.creating: '.ReportStatusLog::class, function (): void {
            throw new RuntimeException('Kegagalan penyimpanan riwayat simulasi');
        });
        $this->actingAs($actor)->post(route($route, $report), $this->payload($route, $to))->assertServerError();
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->assertSame($forwardingsBefore, ReportForwarding::query()->get()->toArray());
        $this->assertDatabaseCount('report_reviews', 0);
        $this->assertDatabaseCount('report_status_logs', 0);
    }

    public function test_cancelled_log_event_also_rolls_back_business_action(): void
    {
        $report = Report::factory()->create();
        Event::listen('eloquent.creating: '.ReportStatusLog::class, fn (): bool => false);
        $this->actingAs(User::factory()->relawan()->create())->post(route('relawan.reports.start-review', $report))->assertServerError();
        $this->assertSame(ReportStatus::Baru, $report->fresh()->status);
        $this->assertDatabaseCount('report_status_logs', 0);
    }

    public function test_outer_transaction_failure_removes_a_log_that_was_already_written(): void
    {
        $report = Report::factory()->create();
        try {
            DB::transaction(function () use ($report): void {
                $report->status = ReportStatus::Ditinjau;
                $report->save();
                $this->assertDatabaseCount('report_status_logs', 1);
                throw new RuntimeException('Kegagalan lanjutan simulasi');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Kegagalan lanjutan simulasi', $exception->getMessage());
        }
        $this->assertSame(ReportStatus::Baru, $report->fresh()->status);
        $this->assertDatabaseCount('report_status_logs', 0);
    }

    public function test_repair_cycle_keeps_order_and_actor_for_each_actual_transition(): void
    {
        $report = Report::factory()->create();
        $volunteer = User::factory()->relawan()->create();
        $coordinator = User::factory()->koordinator()->create();
        $this->actingAs($volunteer)->post(route('relawan.reports.start-review', $report))->assertRedirect();
        $this->post(route('relawan.reports.review', $report), ['decision' => 'perlu_perbaikan', 'note' => 'Mohon perjelas petunjuk lokasi kejadian.'])->assertRedirect();
        $this->actingAs($report->reporter)->post(route('pelapor.reports.resubmit', $report))->assertRedirect();
        $this->actingAs($volunteer)->post(route('relawan.reports.review', $report), ['decision' => 'perlu_diteruskan', 'note' => 'Informasi memadai untuk dicatat penerusannya.'])->assertRedirect();
        $this->actingAs($coordinator)->post(route('koordinator.reports.forward', $report), $this->payload('koordinator.reports.forward'))->assertRedirect();
        $this->post(route('koordinator.reports.close', $report), $this->payload('koordinator.reports.close'))->assertRedirect();
        $logs = $report->statusLogs()->orderBy('id')->get();
        $this->assertSame(['baru', 'ditinjau', 'perlu_perbaikan', 'ditinjau', 'perlu_diteruskan', 'diteruskan'], $logs->map(fn (ReportStatusLog $log): string => $log->from_status->value)->all());
        $this->assertSame(['ditinjau', 'perlu_perbaikan', 'ditinjau', 'perlu_diteruskan', 'diteruskan', 'selesai'], $logs->map(fn (ReportStatusLog $log): string => $log->to_status->value)->all());
        $this->assertSame([$volunteer->id, $volunteer->id, $report->reporter_id, $volunteer->id, $coordinator->id, $coordinator->id], $logs->pluck('changed_by')->all());
    }

    public function test_unauthorized_invalid_and_duplicate_requests_do_not_add_history(): void
    {
        $report = Report::factory()->create();
        $this->actingAs($report->reporter)->postJson(route('relawan.reports.start-review', $report))->assertForbidden();
        $this->assertDatabaseCount('report_status_logs', 0);
        $this->actingAs(User::factory()->relawan()->create())->post(route('relawan.reports.start-review', $report))->assertRedirect();
        $this->postJson(route('relawan.reports.start-review', $report))->assertStatus(409);
        $this->postJson(route('relawan.reports.review', $report), ['decision' => 'selesai', 'note' => ''])->assertUnprocessable();
        $this->assertDatabaseCount('report_status_logs', 1);
        $this->assertSame(ReportStatus::Ditinjau, $report->fresh()->status);
    }

    /** @return array{User, Report} */
    private function prepareTransition(string $route, ReportStatus $from, UserRole $role): array
    {
        $actor = User::factory()->create(['role' => $role]);
        $report = Report::factory()->create(['status' => $from, 'created_at' => now()->subHour(), 'reporter_id' => $role === UserRole::Pelapor ? $actor->id : User::factory()]);
        if ($route === 'koordinator.reports.close') {
            ReportForwarding::factory()->create(['report_id' => $report->id]);
        }

        return [$actor, $report];
    }

    /** @return array<string, string> */
    private function payload(string $route, ?ReportStatus $target = null): array
    {
        return match ($route) {
            'relawan.reports.review' => ['decision' => $target?->value ?? 'perlu_perbaikan', 'note' => 'Catatan peninjauan untuk laporan simulasi.'],
            'koordinator.reports.forward' => ['recipient' => 'Posko simulasi', 'forwarded_at' => now()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i:s'), 'note' => 'Informasi diteruskan untuk simulasi aplikasi.'],
            'koordinator.reports.close' => ['closure_note' => 'Proses pencatatan simulasi telah selesai.'],
            default => [],
        };
    }

    public static function transitions(): array
    {
        return [
            ['relawan.reports.start-review', ReportStatus::Baru, ReportStatus::Ditinjau, UserRole::Relawan],
            ['relawan.reports.review', ReportStatus::Ditinjau, ReportStatus::PerluPerbaikan, UserRole::Relawan],
            ['relawan.reports.review', ReportStatus::Ditinjau, ReportStatus::PerluDiteruskan, UserRole::Relawan],
            ['relawan.reports.review', ReportStatus::Ditinjau, ReportStatus::Selesai, UserRole::Relawan],
            ['pelapor.reports.resubmit', ReportStatus::PerluPerbaikan, ReportStatus::Ditinjau, UserRole::Pelapor],
            ['koordinator.reports.forward', ReportStatus::PerluDiteruskan, ReportStatus::Diteruskan, UserRole::Koordinator],
            ['koordinator.reports.close', ReportStatus::Diteruskan, ReportStatus::Selesai, UserRole::Koordinator],
        ];
    }
}
