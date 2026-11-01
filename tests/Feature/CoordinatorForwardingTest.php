<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreForwardingRequest;
use App\Models\Report;
use App\Models\ReportForwarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CoordinatorForwardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forwarding_uses_trusted_ids_converts_wib_and_changes_status_atomically(): void
    {
        $this->travelTo(now()->startOfSecond());
        $report = $this->readyReport();
        $coordinator = User::factory()->koordinator()->create();
        $original = $report->toArray();
        $payload = $this->payload() + ['report_id' => 999999, 'coordinator_id' => 999999, 'status' => 'selesai', 'created_at' => '2000-01-01', 'title' => 'Judul sisipan', 'closure_note' => 'Penutupan sisipan'];
        $this->actingAs($coordinator)->post(route('koordinator.reports.forward', $report), $payload)
            ->assertRedirect(route('koordinator.reports.show', $report));
        $forwarding = $report->forwarding()->sole();
        $this->assertSame($coordinator->id, $forwarding->coordinator_id);
        $this->assertSame($report->id, $forwarding->report_id);
        $this->assertSame($payload['recipient'], $forwarding->recipient);
        $this->assertSame($payload['note'], $forwarding->note);
        $this->assertTrue($forwarding->forwarded_at->equalTo(now()->subMinute()));
        $this->assertTrue($forwarding->created_at->equalTo(now()));
        $this->assertSame(ReportStatus::Diteruskan, $report->fresh()->status);
        foreach (['title', 'reporter_id', 'area_id', 'description', 'latitude', 'longitude', 'evidence_path', 'closure_note', 'closed_at'] as $field) {
            $this->assertEquals($original[$field], $report->fresh()->toArray()[$field]);
        }
        $this->assertDatabaseCount('report_reviews', 0);
    }

    #[DataProvider('statuses')]
    public function test_only_needs_forwarding_status_is_accepted(ReportStatus $status): void
    {
        $report = $this->readyReport(['status' => $status]);
        $before = $report->toArray();
        $response = $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.forward', $report), $this->payload());
        if ($status === ReportStatus::PerluDiteruskan) {
            $response->assertRedirect();
            $this->assertDatabaseCount('report_forwardings', 1);
        } else {
            $response->assertStatus(409);
            $this->assertEquals($before, $report->fresh()->toArray());
            $this->assertDatabaseCount('report_forwardings', 0);
        }
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_leaves_database_and_storage_unchanged(string $field, mixed $value): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bukti/tetap.txt', 'Bukti tidak berubah');
        $report = $this->readyReport();
        $before = $report->toArray();
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.forward', $report), array_replace($this->payload(), [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->assertDatabaseCount('report_forwardings', 0);
        $this->assertSame(['bukti/tetap.txt'], Storage::disk('local')->allFiles());
        $this->assertSame('Bukti tidak berubah', Storage::disk('local')->get('bukti/tetap.txt'));
    }

    #[DataProvider('timeBounds')]
    public function test_time_boundaries_are_compared_in_utc(int $seconds, bool $allowed): void
    {
        $this->travelTo(now()->startOfSecond());
        $report = $this->readyReport();
        $payload = array_replace($this->payload(), ['forwarded_at' => now()->addSeconds($seconds)->timezone('Asia/Jakarta')->format('Y-m-d\TH:i:s')]);
        $response = $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.forward', $report), $payload);
        if ($allowed) {
            $response->assertRedirect();
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('forwarded_at');
            $this->assertSame(ReportStatus::PerluDiteruskan, $report->fresh()->status);
            $this->assertDatabaseCount('report_forwardings', 0);
        }
    }

    public function test_minute_precision_and_maximum_text_lengths_are_supported(): void
    {
        $report = $this->readyReport();
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.forward', $report), [
            'recipient' => str_repeat('A', 150), 'note' => str_repeat('B', 2000),
            'forwarded_at' => now()->subMinute()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
        ])->assertRedirect();
        $this->assertDatabaseCount('report_forwardings', 1);
    }

    public function test_replay_and_existing_forwarding_cannot_overwrite_first_record(): void
    {
        $report = $this->readyReport();
        $this->actingAs(User::factory()->koordinator()->create())->post(route('koordinator.reports.forward', $report), $this->payload())->assertRedirect();
        $before = $report->forwarding()->sole()->toArray();
        $this->postJson(route('koordinator.reports.forward', $report), $this->payload())->assertStatus(409);
        $report->refresh()->forceFill(['status' => ReportStatus::PerluDiteruskan])->save();
        $this->postJson(route('koordinator.reports.forward', $report), $this->payload())->assertStatus(409);
        $this->assertDatabaseCount('report_forwardings', 1);
        $this->assertEquals($before, $report->forwarding()->sole()->toArray());
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_other_roles_guests_and_inactive_coordinators_cannot_forward(?UserRole $role, bool $active): void
    {
        $report = $this->readyReport();
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $this->postJson(route('koordinator.reports.forward', $report), $this->payload() + ['role' => 'koordinator'])
            ->assertStatus($role === null || ! $active ? 401 : 403);
        $this->assertDatabaseCount('report_forwardings', 0);
        $this->assertSame(ReportStatus::PerluDiteruskan, $report->fresh()->status);
    }

    public function test_status_is_rechecked_after_request_authorization(): void
    {
        $report = $this->readyReport();
        $this->app->afterResolving(StoreForwardingRequest::class, function () use ($report): void {
            $report->forceFill(['status' => ReportStatus::Selesai])->save();
        });
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.forward', $report), $this->payload())->assertStatus(409);
        $this->assertDatabaseCount('report_forwardings', 0);
        $this->assertSame(ReportStatus::Selesai, $report->fresh()->status);
    }

    public function test_existing_forwarding_is_rechecked_after_request_authorization(): void
    {
        $report = $this->readyReport();
        $this->app->afterResolving(StoreForwardingRequest::class, function () use ($report): void {
            ReportForwarding::factory()->create(['report_id' => $report->id]);
        });
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.forward', $report), $this->payload())->assertStatus(409);
        $this->assertDatabaseCount('report_forwardings', 1);
        $this->assertSame(ReportStatus::PerluDiteruskan, $report->fresh()->status);
    }

    public function test_report_creation_time_is_rechecked_inside_transaction(): void
    {
        $report = $this->readyReport();
        $this->app->afterResolving(StoreForwardingRequest::class, function () use ($report): void {
            $report->forceFill(['created_at' => now()])->save();
        });
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.forward', $report), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('forwarded_at');
        $this->assertDatabaseCount('report_forwardings', 0);
    }

    public function test_failed_status_save_rolls_back_forwarding_record(): void
    {
        $report = $this->readyReport();
        Event::listen('eloquent.updating: '.Report::class, function (): void {
            throw new RuntimeException('Kegagalan penyimpanan simulasi');
        });
        $this->actingAs(User::factory()->koordinator()->create())->post(route('koordinator.reports.forward', $report), $this->payload())->assertServerError();
        $this->assertDatabaseCount('report_forwardings', 0);
        $this->assertSame(ReportStatus::PerluDiteruskan, $report->fresh()->status);
    }

    public function test_unknown_report_and_get_request_do_not_create_forwarding(): void
    {
        $report = $this->readyReport();
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.forward', 999999), $this->payload())->assertNotFound();
        $this->get(route('koordinator.reports.forward', $report))->assertStatus(405);
        $this->assertDatabaseCount('report_forwardings', 0);
    }

    public function test_forwarded_report_can_then_be_closed_by_coordinator(): void
    {
        $report = $this->readyReport();
        $this->actingAs(User::factory()->koordinator()->create())->post(route('koordinator.reports.forward', $report), $this->payload())->assertRedirect();
        $this->post(route('koordinator.reports.close', $report), ['closure_note' => 'Proses pencatatan penerusan telah selesai.'])->assertRedirect();
        $this->assertSame(ReportStatus::Selesai, $report->fresh()->status);
        $this->assertDatabaseCount('report_forwardings', 1);
    }

    /** @param array<string, mixed> $attributes */
    private function readyReport(array $attributes = []): Report
    {
        return Report::factory()->create(array_replace(['status' => ReportStatus::PerluDiteruskan, 'created_at' => now()->subHour()], $attributes))->refresh();
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return ['recipient' => 'Posko wilayah simulasi', 'forwarded_at' => now()->subMinute()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i:s'), 'note' => 'SIMULASI: informasi telah diteruskan untuk pencatatan.'];
    }

    public static function statuses(): array
    {
        return array_map(fn (ReportStatus $status): array => [$status], ReportStatus::cases());
    }

    public static function invalidInputs(): array
    {
        return [
            ['recipient', null], ['recipient', '   '], ['recipient', ['palsu']], ['recipient', str_repeat('A', 151)],
            ['note', null], ['note', ['palsu']], ['note', str_repeat('A', 9)], ['note', str_repeat('A', 2001)],
            ['forwarded_at', null], ['forwarded_at', ['palsu']], ['forwarded_at', '2026-02-30T10:00:00'],
            ['forwarded_at', '2026-01-01T10:00:00Z'], ['forwarded_at', 'bukan waktu'],
        ];
    }

    public static function timeBounds(): array
    {
        return [[-3601, false], [-3600, true], [0, true], [1, false]];
    }

    public static function unauthorizedUsers(): array
    {
        return [[null, true], [UserRole::Pelapor, true], [UserRole::Relawan, true], [UserRole::Koordinator, false]];
    }
}
