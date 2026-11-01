<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Http\Requests\ResubmitReportRequest;
use App\Http\Requests\StartReviewRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Area;
use App\Models\Report;
use App\Models\ReportForwarding;
use App\Models\ReportReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_starts_review_once_without_creating_a_decision_or_accepting_payload_status(): void
    {
        $report = Report::factory()->create();
        $this->actingAs(User::factory()->relawan()->create())->post(route('relawan.reports.start-review', $report), [
            'status' => 'selesai', 'closed_at' => now(), 'reviewer_id' => $report->reporter_id,
        ])->assertRedirect(route('relawan.reports.show', $report));
        $this->assertSame(ReportStatus::Ditinjau, $report->fresh()->status);
        $this->assertNull($report->fresh()->closed_at);
        $this->assertSame($report->title, $report->fresh()->title);
        $this->assertDatabaseCount('report_reviews', 0);
        $this->postJson(route('relawan.reports.start-review', $report))->assertStatus(409);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    #[DataProvider('decisions')]
    public function test_decision_and_note_are_saved_with_matching_transition_and_server_author(ReviewDecision $decision, ReportStatus $target): void
    {
        $this->travelTo(now()->startOfSecond());
        $report = Report::factory()->create(['status' => ReportStatus::Ditinjau]);
        $volunteer = User::factory()->relawan()->create();
        $this->actingAs($volunteer)->post(route('relawan.reports.review', $report), [
            'decision' => $decision->value, 'note' => 'Alasan hasil peninjauan dari relawan.', 'status' => 'diteruskan',
            'reviewer_id' => $report->reporter_id, 'report_id' => 999999, 'closed_at' => '2000-01-01',
        ])->assertRedirect(route('relawan.reports.show', $report));
        $review = ReportReview::sole();
        $this->assertSame($volunteer->id, $review->reviewer_id);
        $this->assertSame($report->id, $review->report_id);
        $this->assertSame($decision, $review->decision);
        $this->assertSame('Alasan hasil peninjauan dari relawan.', $review->note);
        $fresh = $report->fresh();
        $this->assertSame($target, $fresh->status);
        $this->assertSame($report->reporter_id, $fresh->reporter_id);
        if ($decision === ReviewDecision::Selesai) {
            $this->assertSame($review->note, $fresh->closure_note);
            $this->assertNotNull($fresh->closed_at);
            $this->assertTrue($fresh->closed_at->equalTo($review->created_at));
        } else {
            $this->assertNull($fresh->closure_note);
            $this->assertNull($fresh->closed_at);
        }
        $this->postJson(route('relawan.reports.review', $report), ['decision' => $decision->value, 'note' => 'Percobaan keputusan ganda.'])->assertStatus(409);
        $this->assertDatabaseCount('report_reviews', 1);
    }

    #[DataProvider('statusMatrix')]
    public function test_each_workflow_action_enforces_its_source_status(string $action, ReportStatus $status, bool $allowed): void
    {
        $report = Report::factory()->create(['status' => $status]);
        $user = $action === 'resubmit' ? $report->reporter : User::factory()->relawan()->create();
        $route = match ($action) {
            'start' => 'relawan.reports.start-review', 'review' => 'relawan.reports.review', 'resubmit' => 'pelapor.reports.resubmit',
        };
        $before = $report->fresh()->toArray();
        $response = $this->actingAs($user)->postJson(route($route, $report), ['decision' => 'perlu_perbaikan', 'note' => 'Mohon perbaiki keterangan lokasi.']);
        if ($allowed) {
            $response->assertRedirect();
        } else {
            $response->assertStatus(409);
            $this->assertEquals($before, $report->fresh()->toArray());
            $this->assertDatabaseCount('report_reviews', 0);
        }
    }

    #[DataProvider('invalidDecisions')]
    public function test_invalid_decision_or_note_does_not_change_report_or_create_review(array $payload, string $field): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Ditinjau]);
        $before = $report->fresh()->toArray();
        $this->actingAs(User::factory()->relawan()->create())->postJson(route('relawan.reports.review', $report), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->assertDatabaseCount('report_reviews', 0);
    }

    #[DataProvider('noteLengths')]
    public function test_note_length_boundaries_are_accepted(int $length): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Ditinjau]);
        $this->actingAs(User::factory()->relawan()->create())->post(route('relawan.reports.review', $report), [
            'decision' => 'perlu_diteruskan', 'note' => str_repeat('A', $length),
        ])->assertRedirect();
        $this->assertSame($length, mb_strlen(ReportReview::sole()->note));
    }

    #[DataProvider('unauthorizedVolunteers')]
    public function test_volunteer_actions_reject_guest_other_roles_and_inactive_volunteer(?UserRole $role, bool $active): void
    {
        $report = Report::factory()->create();
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        foreach (['relawan.reports.start-review', 'relawan.reports.review'] as $route) {
            $this->postJson(route($route, $report), ['role' => 'relawan', 'status' => 'ditinjau', 'decision' => 'selesai', 'note' => 'Catatan sisipan tidak sah.'])
                ->assertStatus($role === null || ! $active ? 401 : 403);
        }
        $this->assertSame(ReportStatus::Baru, $report->fresh()->status);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_reporter_can_resubmit_after_correction_with_history_preserved_and_edit_locked(): void
    {
        $review = ReportReview::factory()->create();
        $report = $review->report;
        $this->actingAs($report->reporter)->post(route('pelapor.reports.resubmit', $report), ['status' => 'selesai', 'title' => 'Sisipan yang diabaikan'])
            ->assertRedirect(route('pelapor.reports.show', $report));
        $this->assertSame(ReportStatus::Ditinjau, $report->fresh()->status);
        $this->assertSame($report->title, $report->fresh()->title);
        $this->assertDatabaseCount('report_reviews', 1);
        $this->assertModelExists($review);
        $this->postJson(route('pelapor.reports.resubmit', $report))->assertStatus(409);
        $this->patchJson(route('pelapor.reports.update', $report), [])->assertStatus(409);
    }

    public function test_multiple_review_rounds_append_notes_without_rewriting_previous_decision(): void
    {
        $report = Report::factory()->create();
        $volunteer = User::factory()->relawan()->create();
        $this->actingAs($volunteer)->post(route('relawan.reports.start-review', $report))->assertRedirect();
        $this->post(route('relawan.reports.review', $report), ['decision' => 'perlu_perbaikan', 'note' => 'Mohon perjelas petunjuk lokasi kejadian.'])->assertRedirect();
        $first = ReportReview::sole();
        $this->actingAs($report->reporter)->post(route('pelapor.reports.resubmit', $report))->assertRedirect();
        $this->actingAs($volunteer)->post(route('relawan.reports.review', $report), [
            'decision' => 'perlu_diteruskan', 'note' => 'Informasi lengkap dan perlu diteruskan kepada pihak terkait.', 'review_id' => $first->id,
        ])->assertRedirect();
        $this->assertDatabaseCount('report_reviews', 2);
        $this->assertSame(ReviewDecision::PerluPerbaikan, $first->fresh()->decision);
        $this->assertSame('Mohon perjelas petunjuk lokasi kejadian.', $first->fresh()->note);
        $this->assertSame(ReportStatus::PerluDiteruskan, $report->fresh()->status);
    }

    public function test_resubmit_uses_current_database_fields_and_cannot_be_repaired_through_payload(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::PerluPerbaikan, 'title' => 'X', 'latitude' => '91']);
        $this->actingAs($report->reporter)->postJson(route('pelapor.reports.resubmit', $report), ['title' => 'Judul valid dari payload', 'latitude' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'latitude']);
        $this->assertSame(ReportStatus::PerluPerbaikan, $report->fresh()->status);
        $this->assertSame('X', $report->fresh()->title);
    }

    public function test_resubmit_allows_retaining_inactive_area_but_rejects_future_observation(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::PerluPerbaikan, 'area_id' => Area::factory()->create(['is_active' => false])->id]);
        $this->actingAs($report->reporter)->post(route('pelapor.reports.resubmit', $report))->assertRedirect();
        $report->refresh();
        $report->status = ReportStatus::PerluPerbaikan;
        $report->occurred_at = now()->addDay();
        $report->save();
        $this->postJson(route('pelapor.reports.resubmit', $report))->assertUnprocessable()->assertJsonValidationErrors('occurred_at');
        $this->assertSame(ReportStatus::PerluPerbaikan, $report->fresh()->status);
    }

    public function test_resubmit_checks_evidence_contents_and_keeps_existing_file(): void
    {
        Storage::fake('local');
        $path = 'report-evidence/simulasi.png';
        Storage::disk('local')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2l9sAAAAASUVORK5CYII='));
        $report = Report::factory()->create(['status' => ReportStatus::PerluPerbaikan, 'evidence_path' => $path, 'evidence_mime' => 'image/png', 'evidence_size' => Storage::disk('local')->size($path)]);
        $this->actingAs($report->reporter)->post(route('pelapor.reports.resubmit', $report))->assertRedirect();
        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $report->fresh()->evidence_path);
    }

    #[DataProvider('badEvidence')]
    public function test_resubmit_rejects_missing_invalid_oversized_or_inconsistent_stored_evidence(string $kind): void
    {
        Storage::fake('local');
        $path = 'report-evidence/bukti.pdf';
        $content = match ($kind) {
            'invalid' => '<?php echo "bukan PDF"; ?>',
            'oversized' => str_pad("%PDF-1.4\n", 2049 * 1024, ' '),
            default => "%PDF-1.4\nData simulasi\n%%EOF",
        };
        Storage::disk('local')->put($path, $content);
        $report = Report::factory()->create(['status' => ReportStatus::PerluPerbaikan, 'evidence_path' => $kind === 'missing' ? 'report-evidence/hilang.pdf' : $path,
            'evidence_mime' => Storage::disk('local')->mimeType($path), 'evidence_size' => $kind === 'metadata' ? 1 : strlen($content)]);
        $this->actingAs($report->reporter)->postJson(route('pelapor.reports.resubmit', $report))
            ->assertUnprocessable()->assertJsonValidationErrors('evidence');
        $this->assertSame(ReportStatus::PerluPerbaikan, $report->fresh()->status);
        Storage::disk('local')->assertExists($path);
    }

    public function test_foreign_or_unknown_reporter_id_cannot_be_resubmitted(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::PerluPerbaikan]);
        $this->actingAs(User::factory()->create());
        foreach ([$report->id, 999999] as $id) {
            $this->postJson(route('pelapor.reports.resubmit', $id), ['reporter_id' => $report->reporter_id])->assertNotFound();
        }
        $this->assertSame(ReportStatus::PerluPerbaikan, $report->fresh()->status);
    }

    #[DataProvider('unauthorizedResubmissions')]
    public function test_resubmit_rejects_guest_staff_and_inactive_owner(?UserRole $role, bool $active): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::PerluPerbaikan]);
        if ($role !== null) {
            $user = $role === UserRole::Pelapor ? $report->reporter : User::factory()->create(['role' => $role]);
            $user->is_active = $active;
            $user->save();
            $this->actingAs($user);
        }
        $this->postJson(route('pelapor.reports.resubmit', $report), ['role' => 'pelapor', 'reporter_id' => $report->reporter_id])
            ->assertStatus($role === null || ! $active ? 401 : 403);
        $this->assertSame(ReportStatus::PerluPerbaikan, $report->fresh()->status);
    }

    #[DataProvider('staleActions')]
    public function test_state_changed_after_initial_authorization_is_rechecked_under_lock(string $requestClass, string $route, ReportStatus $from): void
    {
        $report = Report::factory()->create(['status' => $from]);
        $user = $route === 'pelapor.reports.resubmit' ? $report->reporter : User::factory()->relawan()->create();
        $this->app->afterResolving($requestClass, function () use ($report): void {
            $report->status = ReportStatus::Selesai;
            $report->save();
        });
        $this->actingAs($user)->postJson(route($route, $report), ['decision' => 'perlu_perbaikan', 'note' => 'Mohon perbaiki lokasi laporan.'])->assertStatus(409);
        $this->assertSame(ReportStatus::Selesai, $report->fresh()->status);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_review_and_status_roll_back_together_when_report_save_fails(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Ditinjau]);
        Event::listen('eloquent.updating: '.Report::class, function (): void {
            throw new RuntimeException('Kegagalan penyimpanan laporan simulasi');
        });
        $this->actingAs(User::factory()->relawan()->create())->post(route('relawan.reports.review', $report), ['decision' => 'selesai', 'note' => 'Tidak memerlukan penerusan informasi.'])->assertServerError();
        $this->assertSame(ReportStatus::Ditinjau, $report->fresh()->status);
        $this->assertNull($report->fresh()->closed_at);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_failure_to_save_review_leaves_report_unchanged(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Ditinjau]);
        Event::listen('eloquent.creating: '.ReportReview::class, function (): void {
            throw new RuntimeException('Kegagalan penyimpanan catatan simulasi');
        });
        $this->actingAs(User::factory()->relawan()->create())->post(route('relawan.reports.review', $report), ['decision' => 'perlu_diteruskan', 'note' => 'Perlu diteruskan kepada pihak terkait.'])->assertServerError();
        $this->assertSame(ReportStatus::Ditinjau, $report->fresh()->status);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_volunteer_cannot_close_report_with_existing_forwarding_record(): void
    {
        $forwarding = ReportForwarding::factory()->create();
        $report = $forwarding->report;
        $report->status = ReportStatus::Ditinjau;
        $report->save();
        $this->actingAs(User::factory()->relawan()->create())->postJson(route('relawan.reports.review', $report), ['decision' => 'selesai', 'note' => 'Penutupan yang tidak sah.'])->assertStatus(409);
        $this->assertSame(ReportStatus::Ditinjau, $report->fresh()->status);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_invalid_html_review_redisplays_errors_and_safe_old_input(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Ditinjau]);
        $this->actingAs(User::factory()->relawan()->create())->from(route('relawan.reports.show', $report))
            ->post(route('relawan.reports.review', $report), ['decision' => ['salah'], 'note' => ['salah']])
            ->assertRedirect(route('relawan.reports.show', $report))->assertSessionHasErrors(['decision', 'note']);
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('relawan.reports.show', $report))
            ->assertOk()->assertSee('Keputusan belum disimpan.');
    }

    public static function decisions(): array
    {
        return [[ReviewDecision::PerluPerbaikan, ReportStatus::PerluPerbaikan], [ReviewDecision::PerluDiteruskan, ReportStatus::PerluDiteruskan], [ReviewDecision::Selesai, ReportStatus::Selesai]];
    }

    public static function statusMatrix(): array
    {
        $cases = [];
        foreach (['start' => ReportStatus::Baru, 'review' => ReportStatus::Ditinjau, 'resubmit' => ReportStatus::PerluPerbaikan] as $action => $required) {
            foreach (ReportStatus::cases() as $status) {
                $cases[$action.' '.$status->value] = [$action, $status, $status === $required];
            }
        }

        return $cases;
    }

    public static function invalidDecisions(): array
    {
        $valid = ['decision' => 'perlu_perbaikan', 'note' => 'Mohon perbaiki petunjuk lokasi.'];

        return [[[], 'decision'], [['decision' => 'selesai'], 'note'], [[...$valid, 'decision' => 'diteruskan'], 'decision'], [[...$valid, 'decision' => ['salah']], 'decision'],
            [[...$valid, 'note' => 'pendek'], 'note'], [[...$valid, 'note' => str_repeat('A', 2001)], 'note'], [[...$valid, 'note' => ['salah']], 'note'], [[...$valid, 'note' => ' '], 'note']];
    }

    public static function noteLengths(): array
    {
        return [[10], [2000]];
    }

    public static function unauthorizedVolunteers(): array
    {
        return [[null, true], [UserRole::Pelapor, true], [UserRole::Koordinator, true], [UserRole::Relawan, false]];
    }

    public static function badEvidence(): array
    {
        return [['missing'], ['invalid'], ['oversized'], ['metadata']];
    }

    public static function staleActions(): array
    {
        return [[StartReviewRequest::class, 'relawan.reports.start-review', ReportStatus::Baru], [StoreReviewRequest::class, 'relawan.reports.review', ReportStatus::Ditinjau], [ResubmitReportRequest::class, 'pelapor.reports.resubmit', ReportStatus::PerluPerbaikan]];
    }

    public static function unauthorizedResubmissions(): array
    {
        return [[null, true], [UserRole::Relawan, true], [UserRole::Koordinator, true], [UserRole::Pelapor, false]];
    }
}
