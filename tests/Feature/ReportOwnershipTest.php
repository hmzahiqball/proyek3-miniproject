<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_only_contains_owned_reports_and_ignores_spoofed_owner_parameters(): void
    {
        $owner = User::factory()->create();
        $other = Report::factory()->create(['title' => 'Judul rahasia pelapor lain']);
        $own = Report::factory()->create(['reporter_id' => $owner->id, 'title' => 'Laporan milik sendiri']);

        $this->actingAs($owner)->get(route('pelapor.reports.index', [
            'reporter_id' => $other->reporter_id, 'user_id' => $other->reporter_id, 'role' => 'koordinator',
        ]))->assertOk()->assertSee($own->title)->assertDontSee($other->title)
            ->assertViewHas('reports', fn ($reports): bool => $reports->total() === 1 && $reports->first()->id === $own->id);
    }

    public function test_empty_list_does_not_show_other_users_reports(): void
    {
        $other = Report::factory()->create(['title' => 'Tidak boleh terlihat']);
        $this->actingAs(User::factory()->create())->get(route('pelapor.reports.index'))
            ->assertOk()->assertSee('Anda belum mengirim laporan.')->assertDontSee($other->title)
            ->assertViewHas('reports', fn ($reports): bool => $reports->total() === 0);
    }

    public function test_pagination_preserves_ownership_and_orders_newest_first_with_a_stable_tie_breaker(): void
    {
        $owner = User::factory()->create();
        $area = Area::factory()->create();
        $own = Report::factory()->count(11)->create([
            'reporter_id' => $owner->id, 'area_id' => $area->id, 'created_at' => now()->subDay(),
        ]);
        Report::factory()->count(2)->create(['title' => 'Rahasia pada halaman lain']);

        $this->actingAs($owner)->get(route('pelapor.reports.index'))->assertOk()
            ->assertDontSee('Rahasia pada halaman lain')
            ->assertViewHas('reports', fn ($reports): bool => $reports->total() === 11 && $reports->count() === 10
                && $reports->first()->id === $own->last()->id && $reports->last()->id === $own[1]->id);
        $this->get(route('pelapor.reports.index', ['page' => 2, 'reporter_id' => 999999]))->assertOk()
            ->assertDontSee('Rahasia pada halaman lain')
            ->assertViewHas('reports', fn ($reports): bool => $reports->total() === 11 && $reports->count() === 1
                && $reports->first()->id === $own->first()->id);
    }

    public function test_owner_can_view_detail_with_wib_time_and_without_private_storage_metadata(): void
    {
        $report = Report::factory()->create([
            'occurred_at' => '2026-11-01 18:00:00', 'created_at' => '2026-11-01 18:30:00',
            'evidence_path' => 'private/rahasia-bukti.pdf', 'evidence_mime' => 'application/pdf', 'evidence_size' => 1024,
        ]);
        $this->actingAs($report->reporter)->get(route('pelapor.reports.show', $report))->assertOk()
            ->assertSee($report->title)->assertSee($report->description)->assertSee($report->location_description)
            ->assertSee($report->area->name)->assertSee($report->latitude)->assertSee($report->longitude)
            ->assertSee('02/11/2026 01:00 WIB')->assertSee('02/11/2026 01:30 WIB')
            ->assertDontSee('private/rahasia-bukti.pdf')->assertDontSee($report->reporter->email);
    }

    public function test_foreign_and_unknown_report_ids_return_404_without_leaking_content_or_mutating_data(): void
    {
        $report = Report::factory()->create(['title' => 'Judul laporan rahasia']);
        $before = $report->fresh()->toArray();
        $this->actingAs(User::factory()->create());
        foreach ([$report->id, 999999] as $id) {
            $this->get(route('pelapor.reports.show', $id))->assertNotFound()->assertDontSee($report->title);
            $this->getJson(route('pelapor.reports.show', $id))->assertNotFound()->assertDontSee($report->title);
        }
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_report_owner_parameters_and_role_headers_cannot_bypass_detail_scope(): void
    {
        $report = Report::factory()->create();
        $this->actingAs(User::factory()->create())->withHeader('X-Role', 'koordinator')->get(route('pelapor.reports.show', [
            'report' => $report->id, 'reporter_id' => $report->reporter_id, 'user_id' => $report->reporter_id,
        ]))->assertNotFound()->assertDontSee($report->description);
    }

    public function test_inactive_area_does_not_hide_historical_reports(): void
    {
        $report = Report::factory()->create(['area_id' => Area::factory()->create(['is_active' => false])->id]);
        $this->actingAs($report->reporter)->get(route('pelapor.reports.index'))->assertOk()
            ->assertSee($report->title)->assertSee('wilayah nonaktif');
        $this->get(route('pelapor.reports.show', $report))->assertOk()
            ->assertSee($report->area->name)->assertSee('Wilayah saat ini nonaktif.');
    }

    #[DataProvider('statuses')]
    public function test_owner_can_read_report_at_every_status(ReportStatus $status): void
    {
        $report = Report::factory()->create(['status' => $status]);
        $this->actingAs($report->reporter)->get(route('pelapor.reports.index'))->assertOk()->assertSee($status->label());
        $this->get(route('pelapor.reports.show', $report))->assertOk()->assertSee($status->label());
        $this->assertSame($status, $report->fresh()->status);
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_guests_other_roles_and_inactive_accounts_cannot_use_reporter_read_routes(?UserRole $role, bool $active): void
    {
        $report = Report::factory()->create();
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        foreach ([route('pelapor.reports.index'), route('pelapor.reports.show', $report)] as $url) {
            $this->getJson($url)->assertStatus($role === null || ! $active ? 401 : 403)->assertDontSee($report->title);
        }
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_guest_html_redirects_to_login(): void
    {
        $report = Report::factory()->create();
        $this->get(route('pelapor.reports.index'))->assertRedirect(route('access'));
        $this->get(route('pelapor.reports.show', $report))->assertRedirect(route('access'));
    }

    public function test_policy_itself_checks_role_active_account_and_owner(): void
    {
        $report = Report::factory()->create();
        $this->assertTrue(Gate::forUser($report->reporter)->allows('view', $report));
        $this->assertTrue(Gate::forUser($report->reporter)->allows('viewAny', Report::class));
        foreach ([User::factory()->create(), User::factory()->relawan()->create(), User::factory()->koordinator()->create()] as $user) {
            $decision = Gate::forUser($user)->inspect('view', $report);
            $this->assertFalse($decision->allowed());
            $this->assertSame(404, $decision->status());
        }
        $report->reporter->is_active = false;
        $this->assertTrue(Gate::forUser($report->reporter)->denies('view', $report));
        $this->assertTrue(Gate::forUser($report->reporter)->denies('viewAny', Report::class));
    }

    public function test_list_and_detail_escape_report_content(): void
    {
        $report = Report::factory()->create(['title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>']);
        $this->actingAs($report->reporter)->get(route('pelapor.reports.index'))->assertOk()
            ->assertSee($report->title)->assertDontSee($report->title, false);
        $this->get(route('pelapor.reports.show', $report))->assertOk()->assertSee($report->description)
            ->assertDontSee($report->title, false)->assertDontSee($report->description, false);
    }

    public static function statuses(): array
    {
        return array_map(fn (ReportStatus $status): array => [$status], ReportStatus::cases());
    }

    public static function unauthorizedUsers(): array
    {
        return [
            'guest' => [null, true], 'relawan' => [UserRole::Relawan, true],
            'koordinator' => [UserRole::Koordinator, true], 'pelapor nonaktif' => [UserRole::Pelapor, false],
        ];
    }
}
