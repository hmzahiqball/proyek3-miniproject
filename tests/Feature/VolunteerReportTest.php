<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\ReportReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VolunteerReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_list_contains_reports_from_different_reporters_and_areas(): void
    {
        $first = Report::factory()->create(['title' => 'Asap dari Pelapor Pertama']);
        $second = Report::factory()->create(['title' => 'Asap dari Pelapor Kedua']);
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.index', ['reporter_id' => $first->reporter_id]))
            ->assertOk()->assertSee($first->title)->assertSee($second->title)
            ->assertSee($first->area->name)->assertSee($second->area->name)
            ->assertViewHas('reports', fn ($reports): bool => $reports->total() === 2 && $reports->first()->id === $second->id);
    }

    public function test_list_pagination_has_stable_newest_order_and_no_duplicate_report_on_second_page(): void
    {
        $reports = Report::factory()->count(11)->create(['area_id' => Area::factory()->create()->id, 'created_at' => now()->subDay()]);
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.index'))->assertOk()
            ->assertViewHas('reports', fn ($page): bool => $page->total() === 11 && $page->count() === 10 && $page->first()->id === $reports->last()->id);
        $this->get(route('relawan.reports.index', ['page' => 2]))->assertOk()
            ->assertViewHas('reports', fn ($page): bool => $page->count() === 1 && $page->first()->id === $reports->first()->id);
    }

    public function test_empty_list_explains_that_no_report_has_arrived(): void
    {
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.index'))
            ->assertOk()->assertSee('Belum ada laporan yang masuk.');
    }

    #[DataProvider('statuses')]
    public function test_volunteer_can_read_every_status_without_mutating_report(ReportStatus $status): void
    {
        $report = Report::factory()->create(['status' => $status, 'evidence_path' => 'report-evidence/rahasia.pdf', 'evidence_mime' => 'application/pdf', 'evidence_size' => 100]);
        $before = $report->fresh()->toArray();
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.show', $report))
            ->assertOk()->assertSee($report->title)->assertSee($report->description)->assertSee($report->location_description)
            ->assertSee($report->latitude)->assertSee($report->longitude)->assertSee($report->reporter->name)
            ->assertSee($status->label())->assertSee(route('reports.evidence', $report))
            ->assertDontSee($report->evidence_path)->assertDontSee($report->reporter->email)->assertDontSee('Perbaiki laporan');
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->assertDatabaseCount('report_reviews', 0);
        $this->assertDatabaseCount('report_status_logs', 0);
    }

    public function test_related_reports_are_limited_to_current_area_exclude_current_report_and_ignore_spoofed_area(): void
    {
        $current = Report::factory()->create();
        $related = Report::factory()->create(['area_id' => $current->area_id, 'title' => 'Laporan terkait yang sesuai']);
        $unrelated = Report::factory()->create(['title' => 'Laporan dari wilayah berbeda']);
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.show', [
            'report' => $current->id, 'area_id' => $unrelated->area_id, 'reporter_id' => $unrelated->reporter_id,
        ]))->assertOk()->assertSee($related->title)->assertDontSee($unrelated->title)
            ->assertViewHas('relatedReports', fn ($reports): bool => $reports->total() === 1 && $reports->first()->id === $related->id);
    }

    public function test_related_pagination_preserves_area_scope_on_second_page(): void
    {
        $current = Report::factory()->create();
        $related = Report::factory()->count(6)->create(['area_id' => $current->area_id, 'created_at' => now()->subDay()]);
        $unrelated = Report::factory()->create(['title' => 'Judul wilayah lain yang tidak terkait']);
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.show', $current))->assertOk()
            ->assertViewHas('relatedReports', fn ($page): bool => $page->total() === 6 && $page->count() === 5 && $page->first()->id === $related->last()->id);
        $this->get(route('relawan.reports.show', ['report' => $current->id, 'terkait' => 2, 'area_id' => $unrelated->area_id]))
            ->assertOk()->assertDontSee($unrelated->title)
            ->assertViewHas('relatedReports', fn ($page): bool => $page->total() === 6 && $page->count() === 1 && $page->first()->id === $related->first()->id);
    }

    public function test_inactive_area_keeps_report_and_related_history_visible(): void
    {
        $area = Area::factory()->create(['is_active' => false]);
        $report = Report::factory()->create(['area_id' => $area->id]);
        $related = Report::factory()->create(['area_id' => $area->id, 'title' => 'Riwayat terkait wilayah nonaktif']);
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.index'))->assertOk()->assertSee('wilayah nonaktif');
        $this->get(route('relawan.reports.show', $report))->assertOk()->assertSee('Wilayah saat ini nonaktif.')->assertSee($related->title);
    }

    #[DataProvider('decisions')]
    public function test_review_history_shows_decision_author_note_and_wib_time(ReviewDecision $decision): void
    {
        $review = ReportReview::factory()->create(['decision' => $decision, 'created_at' => '2026-11-01 18:00:00']);
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.show', $review->report))
            ->assertOk()->assertSee($decision->label())->assertSee($review->note)->assertSee($review->reviewer->name)
            ->assertSee('02/11/2026 01:00 WIB')->assertDontSee($review->reviewer->email);
        $this->assertDatabaseCount('report_reviews', 1);
    }

    public function test_review_history_paginates_only_current_report_with_newest_note_first(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::PerluPerbaikan]);
        $reviewer = User::factory()->relawan()->create();
        $reviews = ReportReview::factory()->count(11)->create(['report_id' => $report->id, 'reviewer_id' => $reviewer->id, 'created_at' => now()->subDay()]);
        $other = ReportReview::factory()->create(['note' => 'Catatan rahasia untuk laporan yang berbeda']);
        $this->actingAs($reviewer)->get(route('relawan.reports.show', $report))->assertOk()->assertDontSee($other->note)
            ->assertViewHas('reviews', fn ($page): bool => $page->total() === 11 && $page->count() === 10 && $page->first()->id === $reviews->last()->id);
        $this->get(route('relawan.reports.show', ['report' => $report->id, 'catatan' => 2, 'report_id' => $other->report_id]))
            ->assertOk()->assertDontSee($other->note)
            ->assertViewHas('reviews', fn ($page): bool => $page->count() === 1 && $page->first()->id === $reviews->first()->id);
    }

    public function test_reporter_can_only_read_review_history_of_own_report(): void
    {
        $own = ReportReview::factory()->create(['note' => 'Mohon perbaiki petunjuk lokasi laporan Anda.']);
        $other = ReportReview::factory()->create(['note' => 'Catatan khusus milik Pelapor lain.']);
        $this->actingAs($own->report->reporter)->get(route('pelapor.reports.show', ['report' => $own->report_id, 'report_id' => $other->report_id]))
            ->assertOk()->assertSee($own->note)->assertDontSee($other->note)
            ->assertDontSee('Laporan terkait di wilayah yang sama');
        $this->get(route('pelapor.reports.show', $other->report_id))->assertNotFound()->assertDontSee($other->note);
        $this->get(route('relawan.reports.show', $other->report_id))->assertForbidden();
    }

    public function test_detail_has_truthful_empty_states_without_fabricated_notes_or_related_reports(): void
    {
        $report = Report::factory()->create();
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.show', $report))
            ->assertOk()->assertSee('Belum ada catatan peninjauan.')->assertSee('Belum ada laporan lain di wilayah ini.');
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_guest_other_roles_and_inactive_volunteer_are_rejected(?UserRole $role, bool $active): void
    {
        $report = Report::factory()->create(['title' => 'Judul laporan terbatas']);
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $status = $role === null || ! $active ? 401 : 403;
        $this->getJson(route('relawan.reports.index'))->assertStatus($status)->assertDontSee($report->title);
        $this->getJson(route('relawan.reports.show', ['report' => $report->id, 'role' => 'relawan']))
            ->assertStatus($status)->assertDontSee($report->title);
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_guest_html_is_redirected_and_unknown_id_returns_404_for_volunteer(): void
    {
        $report = Report::factory()->create();
        $this->get(route('relawan.reports.index'))->assertRedirect(route('access'));
        $this->get(route('relawan.reports.show', $report))->assertRedirect(route('access'));
        $this->actingAs(User::factory()->relawan()->create())->get(route('relawan.reports.show', 999999))->assertNotFound();
    }

    public function test_read_routes_do_not_accept_post_patch_or_delete_and_volunteer_cannot_edit_reporter_content(): void
    {
        $report = Report::factory()->create();
        $before = $report->fresh()->toArray();
        $this->actingAs(User::factory()->relawan()->create());
        foreach (['post', 'patch', 'delete'] as $method) {
            $this->{$method}(route('relawan.reports.show', $report), ['status' => 'selesai', 'note' => 'Catatan sisipan'])
                ->assertStatus(405);
        }
        $this->patchJson(route('pelapor.reports.update', $report), ['role' => 'pelapor', 'title' => 'Diganti relawan'])->assertForbidden();
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->assertDatabaseCount('report_reviews', 0);
    }

    public function test_monitor_policy_requires_active_volunteer_without_granting_reporter_edit_access(): void
    {
        $report = Report::factory()->create();
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertSame($role === UserRole::Relawan, Gate::forUser($user)->allows('monitorAny', Report::class));
            $this->assertSame($role === UserRole::Relawan, Gate::forUser($user)->allows('monitor', $report));
            $this->assertFalse(Gate::forUser($user)->allows('update', $report));
        }
        $inactive = User::factory()->relawan()->inactive()->create();
        $this->assertTrue(Gate::forUser($inactive)->denies('monitorAny', Report::class));
        $this->assertTrue(Gate::forUser($inactive)->denies('monitor', $report));
    }

    public function test_report_and_review_text_are_escaped_on_both_role_views(): void
    {
        $review = ReportReview::factory()->create(['note' => '<script>alert(1)</script>']);
        $review->report->update(['title' => '<img src=x onerror=alert(1)>']);
        $this->actingAs($review->reviewer)->get(route('relawan.reports.show', $review->report))
            ->assertOk()->assertSee($review->note)->assertDontSee($review->note, false)->assertDontSee($review->report->title, false);
        $this->actingAs($review->report->reporter)->get(route('pelapor.reports.show', $review->report))
            ->assertOk()->assertSee($review->note)->assertDontSee($review->note, false);
    }

    public static function statuses(): array
    {
        return array_map(fn (ReportStatus $status): array => [$status], ReportStatus::cases());
    }

    public static function decisions(): array
    {
        return array_map(fn (ReviewDecision $decision): array => [$decision], ReviewDecision::cases());
    }

    public static function unauthorizedUsers(): array
    {
        return [[null, true], [UserRole::Pelapor, true], [UserRole::Koordinator, true], [UserRole::Relawan, false]];
    }
}
