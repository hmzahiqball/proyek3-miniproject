<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Requests\CloseForwardedReportRequest;
use App\Models\Report;
use App\Models\ReportForwarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CoordinatorClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_closes_forwarded_report_with_server_time_without_creating_volunteer_review(): void
    {
        $this->travelTo(now()->startOfSecond());
        $forwarding = ReportForwarding::factory()->create();
        $report = $forwarding->report;
        $note = 'Proses penerusan telah ditindaklanjuti dan pencatatan selesai.';
        $this->actingAs(User::factory()->koordinator()->create())->post(route('koordinator.reports.close', $report), [
            'closure_note' => $note, 'status' => 'baru', 'closed_at' => '2000-01-01', 'reporter_id' => 999999,
        ])->assertRedirect(route('koordinator.reports.show', $report));
        $fresh = $report->fresh();
        $this->assertSame(ReportStatus::Selesai, $fresh->status);
        $this->assertSame($note, $fresh->closure_note);
        $this->assertTrue($fresh->closed_at->equalTo(now()));
        $this->assertSame($report->reporter_id, $fresh->reporter_id);
        $this->assertModelExists($forwarding);
        $this->assertDatabaseCount('report_reviews', 0);
        $this->get(route('koordinator.reports.show', $report))->assertOk()->assertSee($note)->assertDontSee('Tutup laporan');
        $this->actingAs($report->reporter)->get(route('pelapor.reports.show', $report))->assertOk()->assertSee($note);
    }

    #[DataProvider('sourceStatuses')]
    public function test_closure_only_accepts_forwarded_status_even_when_forwarding_exists(ReportStatus $status): void
    {
        $forwarding = ReportForwarding::factory()->create(['report_id' => Report::factory()->create(['status' => $status])->id]);
        $report = $forwarding->report;
        $before = $report->fresh()->toArray();
        $response = $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.close', $report), ['closure_note' => 'Catatan penyelesaian proses yang sah.']);
        if ($status === ReportStatus::Diteruskan) {
            $response->assertRedirect();
        } else {
            $response->assertStatus(409);
            $this->assertEquals($before, $report->fresh()->toArray());
        }
    }

    public function test_missing_forwarding_record_cannot_be_replaced_with_payload_id(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Diteruskan]);
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.close', $report), [
            'closure_note' => 'Percobaan menutup tanpa catatan penerusan.', 'forwarding_id' => 123, 'recipient' => 'Tujuan palsu',
        ])->assertStatus(409);
        $this->assertSame(ReportStatus::Diteruskan, $report->fresh()->status);
        $this->assertNull($report->fresh()->closed_at);
        $this->assertDatabaseCount('report_forwardings', 0);
    }

    #[DataProvider('invalidNotes')]
    public function test_invalid_closure_note_keeps_forwarded_report_unchanged(mixed $note): void
    {
        $report = ReportForwarding::factory()->create()->report;
        $before = $report->fresh()->toArray();
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.close', $report), ['closure_note' => $note])
            ->assertUnprocessable()->assertJsonValidationErrors('closure_note');
        $this->assertEquals($before, $report->fresh()->toArray());
    }

    #[DataProvider('validNoteLengths')]
    public function test_closure_note_boundary_lengths_are_valid(int $length): void
    {
        $report = ReportForwarding::factory()->create()->report;
        $this->actingAs(User::factory()->koordinator()->create())->post(route('koordinator.reports.close', $report), ['closure_note' => str_repeat('A', $length)])->assertRedirect();
        $this->assertSame($length, mb_strlen($report->fresh()->closure_note));
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_other_roles_and_inactive_coordinator_cannot_read_or_close(?UserRole $role, bool $active): void
    {
        $report = ReportForwarding::factory()->create()->report;
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $status = $role === null || ! $active ? 401 : 403;
        $this->getJson(route('koordinator.reports.index'))->assertStatus($status);
        $this->getJson(route('koordinator.reports.show', $report))->assertStatus($status);
        $this->postJson(route('koordinator.reports.close', $report), ['role' => 'koordinator', 'closure_note' => 'Sisipan catatan yang tidak sah.'])->assertStatus($status);
        $this->assertSame(ReportStatus::Diteruskan, $report->fresh()->status);
        $this->assertNull($report->fresh()->closure_note);
    }

    public function test_duplicate_closure_is_rejected_and_preserves_first_note_and_time(): void
    {
        $report = ReportForwarding::factory()->create()->report;
        $this->actingAs(User::factory()->koordinator()->create())->post(route('koordinator.reports.close', $report), ['closure_note' => 'Catatan penyelesaian pertama.'])->assertRedirect();
        $before = $report->fresh()->toArray();
        $this->postJson(route('koordinator.reports.close', $report), ['closure_note' => 'Catatan kedua yang ditolak.'])->assertStatus(409);
        $this->assertEquals($before, $report->fresh()->toArray());
    }

    public function test_status_changed_after_authorization_cannot_be_overwritten(): void
    {
        $report = ReportForwarding::factory()->create()->report;
        $this->app->afterResolving(CloseForwardedReportRequest::class, function () use ($report): void {
            $report->status = ReportStatus::Selesai;
            $report->closure_note = 'Sudah ditutup dari permintaan lain.';
            $report->closed_at = now();
            $report->save();
        });
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.close', $report), ['closure_note' => 'Catatan dari halaman yang sudah lama.'])->assertStatus(409);
        $this->assertSame('Sudah ditutup dari permintaan lain.', $report->fresh()->closure_note);
    }

    public function test_failed_save_rolls_back_closure_fields_and_preserves_forwarding(): void
    {
        $forwarding = ReportForwarding::factory()->create();
        $report = $forwarding->report;
        Event::listen('eloquent.updating: '.Report::class, function (): void {
            throw new RuntimeException('Kegagalan penutupan simulasi');
        });
        $this->actingAs(User::factory()->koordinator()->create())->post(route('koordinator.reports.close', $report), ['closure_note' => 'Catatan yang gagal disimpan.'])->assertServerError();
        $this->assertSame(ReportStatus::Diteruskan, $report->fresh()->status);
        $this->assertNull($report->fresh()->closure_note);
        $this->assertNull($report->fresh()->closed_at);
        $this->assertModelExists($forwarding);
    }

    public function test_unknown_id_is_not_found_and_get_does_not_close_a_report(): void
    {
        $report = ReportForwarding::factory()->create()->report;
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.reports.close', 999999), ['closure_note' => 'Catatan untuk laporan yang tidak ada.'])->assertNotFound();
        $this->get(route('koordinator.reports.close', $report))->assertStatus(405);
        $this->assertSame(ReportStatus::Diteruskan, $report->fresh()->status);
    }

    public function test_coordinator_can_read_reports_history_forwarding_and_related_data_without_edit_privileges(): void
    {
        $forwarding = ReportForwarding::factory()->create();
        $report = $forwarding->report;
        $related = Report::factory()->create(['area_id' => $report->area_id, 'title' => 'Laporan terkait untuk Koordinator']);
        $other = Report::factory()->create(['title' => 'Laporan dari wilayah yang berbeda']);
        $this->actingAs(User::factory()->koordinator()->create())->get(route('koordinator.reports.index'))->assertOk()->assertSee($report->title)->assertSee($other->title);
        $this->get(route('koordinator.reports.show', $report))->assertOk()->assertSee($forwarding->recipient)
            ->assertSee($forwarding->note)->assertSee($related->title)->assertDontSee($other->title)
            ->assertSee('Tutup laporan')->assertDontSee('Perbaiki laporan')->assertSee(route('koordinator.reports.show', $related));
        $this->get(route('relawan.reports.show', $report))->assertForbidden();
        $this->patchJson(route('pelapor.reports.update', $report), [])->assertForbidden();
    }

    public function test_closure_html_validation_redisplays_safe_input(): void
    {
        $report = ReportForwarding::factory()->create()->report;
        $this->actingAs(User::factory()->koordinator()->create())->from(route('koordinator.reports.show', $report))
            ->post(route('koordinator.reports.close', $report), ['closure_note' => ['salah']])
            ->assertRedirect(route('koordinator.reports.show', $report))->assertSessionHasErrors('closure_note');
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('koordinator.reports.show', $report))
            ->assertOk()->assertSee('Laporan belum ditutup.');
    }

    public static function sourceStatuses(): array
    {
        return array_map(fn (ReportStatus $status): array => [$status], ReportStatus::cases());
    }

    public static function invalidNotes(): array
    {
        return [[null], [' '], ['pendek'], [str_repeat('A', 2001)], [['salah']]];
    }

    public static function validNoteLengths(): array
    {
        return [[10], [2000]];
    }

    public static function unauthorizedRoles(): array
    {
        return [[null, true], [UserRole::Pelapor, true], [UserRole::Relawan, true], [UserRole::Koordinator, false]];
    }
}
