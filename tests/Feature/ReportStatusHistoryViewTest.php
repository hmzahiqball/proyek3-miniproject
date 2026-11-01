<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportStatusHistoryViewTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_authorized_detail_shows_statuses_actor_wib_time_and_only_this_reports_history(UserRole $role, string $route): void
    {
        $user = User::factory()->create(['role' => $role]);
        $report = Report::factory()->create(['reporter_id' => $user->id, 'status' => ReportStatus::Ditinjau]);
        $actor = User::factory()->relawan()->create(['name' => '<script>Nama pengubah</script>']);
        ReportStatusLog::factory()->create(['report_id' => $report->id, 'changed_by' => $actor->id, 'created_at' => '2026-11-01 10:12:34']);
        $foreignActor = User::factory()->relawan()->create(['name' => 'Pengubah laporan lain']);
        ReportStatusLog::factory()->create(['changed_by' => $foreignActor->id]);
        $this->actingAs($user)->get(route($route, $report))->assertOk()->assertSee('Riwayat perubahan status')
            ->assertSee('Baru → Ditinjau')->assertSee('01/11/2026 17:12:34 WIB')
            ->assertSee('&lt;script&gt;Nama pengubah&lt;/script&gt;', false)->assertDontSee('<script>Nama pengubah</script>', false)
            ->assertDontSee($actor->email)->assertDontSee($foreignActor->name)
            ->assertViewHas('statusLogs', fn ($logs): bool => $logs->total() === 1);
        $this->assertDatabaseCount('report_status_logs', 2);
    }

    public function test_owner_cannot_access_another_reports_history_by_changing_report_id_or_query(): void
    {
        $foreign = ReportStatusLog::factory()->create();
        $own = Report::factory()->create();
        $this->actingAs($own->reporter)->get(route('pelapor.reports.show', $foreign->report_id))->assertNotFound();
        $this->get(route('pelapor.reports.show', [$own, 'report_id' => $foreign->report_id, 'riwayat' => 1]))
            ->assertOk()->assertViewHas('statusLogs', fn ($logs): bool => $logs->total() === 0);
        $this->assertDatabaseCount('report_status_logs', 1);
    }

    public function test_system_actor_and_empty_history_are_explained_without_backfilling_old_reports(): void
    {
        $log = ReportStatusLog::factory()->system()->create();
        $this->actingAs($log->report->reporter)->get(route('pelapor.reports.show', $log->report))
            ->assertOk()->assertSee('Sistem ·');
        $oldReport = Report::factory()->create(['status' => ReportStatus::Selesai]);
        $this->actingAs($oldReport->reporter)->get(route('pelapor.reports.show', $oldReport))
            ->assertOk()->assertSee('Belum ada perubahan status yang tercatat.');
        $this->assertDatabaseCount('report_status_logs', 1);
    }

    #[DataProvider('roles')]
    public function test_history_is_paginated_with_its_own_parameter_and_stable_latest_order(UserRole $role, string $route): void
    {
        $user = User::factory()->create(['role' => $role]);
        $report = Report::factory()->create(['reporter_id' => $user->id, 'status' => ReportStatus::Ditinjau]);
        $actor = User::factory()->relawan()->create();
        for ($index = 0; $index < 11; $index++) {
            $log = ReportStatusLog::factory()->create([
                'report_id' => $report->id, 'changed_by' => $actor->id, 'created_at' => now()->startOfSecond(),
                'from_status' => $index === 0 ? ReportStatus::Baru : ($index % 2 === 1 ? ReportStatus::Ditinjau : ReportStatus::PerluPerbaikan),
                'to_status' => $index % 2 === 0 ? ReportStatus::Ditinjau : ReportStatus::PerluPerbaikan,
            ]);
            $firstId ??= $log->id;
        }
        $response = $this->actingAs($user)->get(route($route, $report))->assertOk()
            ->assertViewHas('statusLogs', fn ($logs): bool => $logs->total() === 11 && $logs->count() === 10 && $logs->first()->id === $log->id);
        $nextPage = $response->viewData('statusLogs')->nextPageUrl();
        $this->assertStringContainsString('riwayat=2', $nextPage);
        $this->get($nextPage)->assertOk()->assertViewHas('statusLogs', fn ($logs): bool => $logs->count() === 1 && $logs->first()->id === $firstId);
        $this->assertDatabaseCount('report_status_logs', 11);
    }

    public static function roles(): array
    {
        return [[UserRole::Pelapor, 'pelapor.reports.show'], [UserRole::Relawan, 'relawan.reports.show'], [UserRole::Koordinator, 'koordinator.reports.show']];
    }
}
