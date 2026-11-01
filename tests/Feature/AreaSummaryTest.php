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

class AreaSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporter_summary_only_counts_own_unfinished_reports_in_selected_area(): void
    {
        $reporter = User::factory()->create();
        $area = Area::factory()->create();
        foreach (ReportStatus::cases() as $status) {
            Report::factory()->create(['reporter_id' => $reporter->id, 'area_id' => $area->id, 'status' => $status]);
        }
        $foreign = Report::factory()->create(['area_id' => $area->id, 'title' => 'Judul pribadi pengguna lain', 'evidence_path' => 'bukti/rahasia.pdf']);
        Report::factory()->create(['reporter_id' => $reporter->id]);
        $this->actingAs($reporter)->getJson(route('areas.summary', $area).'?scope=seluruh_laporan&reporter_id='.$foreign->reporter_id.'&role=koordinator&status=selesai')
            ->assertOk()->assertExactJson(['data' => [
                'area_id' => $area->id, 'area_name' => $area->name, 'active_report_count' => 5, 'scope' => 'milik_sendiri',
            ]])->assertDontSee('Judul pribadi pengguna lain')->assertDontSee('rahasia.pdf')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('reports', 8);
        $this->assertDatabaseCount('report_reviews', 0);
        $this->assertDatabaseCount('report_forwardings', 0);
        $this->assertDatabaseCount('report_status_logs', 0);
    }

    #[DataProvider('staffRoles')]
    public function test_staff_summary_counts_all_unfinished_reports_in_area(UserRole $role): void
    {
        $area = Area::factory()->create();
        Report::factory()->count(2)->create(['area_id' => $area->id]);
        Report::factory()->create(['area_id' => $area->id, 'status' => ReportStatus::Selesai]);
        Report::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]))->getJson(route('areas.summary', $area))
            ->assertOk()->assertExactJson(['data' => [
                'area_id' => $area->id, 'area_name' => $area->name, 'active_report_count' => 2, 'scope' => 'seluruh_laporan',
            ]]);
    }

    public function test_no_own_active_reports_returns_zero_even_when_other_reports_exist(): void
    {
        $area = Area::factory()->create();
        Report::factory()->create(['area_id' => $area->id]);
        $this->actingAs(User::factory()->create())->getJson(route('areas.summary', $area))
            ->assertOk()->assertJsonPath('data.active_report_count', 0);
    }

    public function test_inactive_area_remains_readable_for_existing_report_editing(): void
    {
        $report = Report::factory()->create(['area_id' => Area::factory()->create(['is_active' => false])->id]);
        $this->actingAs($report->reporter)->getJson(route('areas.summary', $report->area_id))
            ->assertOk()->assertJsonPath('data.active_report_count', 1)->assertJsonPath('data.scope', 'milik_sendiri');
    }

    public function test_unknown_or_malformed_area_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('areas.summary', 999999))->assertNotFound();
        $this->getJson('/wilayah/bukan-angka/ringkasan')->assertNotFound();
    }

    public function test_guest_json_is_unauthorized_and_html_redirects_to_login(): void
    {
        $area = Area::factory()->create();
        $this->getJson(route('areas.summary', $area))->assertUnauthorized();
        $this->get(route('areas.summary', $area))->assertRedirect(route('access'));
    }

    #[DataProvider('allRoles')]
    public function test_inactive_users_cannot_read_summary(UserRole $role): void
    {
        $area = Area::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => false]))
            ->getJson(route('areas.summary', $area))->assertUnauthorized();
    }

    public function test_write_methods_are_not_supported(): void
    {
        $area = Area::factory()->create();
        $this->actingAs(User::factory()->koordinator()->create());
        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->{$method}(route('areas.summary', $area), ['name' => 'Perubahan ilegal'])->assertStatus(405);
        }
        $this->assertSame($area->name, $area->fresh()->name);
    }

    public static function staffRoles(): array
    {
        return [[UserRole::Relawan], [UserRole::Koordinator]];
    }

    public static function allRoles(): array
    {
        return array_map(fn (UserRole $role): array => [$role], UserRole::cases());
    }
}
