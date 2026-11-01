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

class ReportFilteringTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_search_and_filters_are_combined_without_leaking_foreign_reports(UserRole $role, string $route): void
    {
        $user = User::factory()->create(['role' => $role]);
        $area = Area::factory()->create(['is_active' => false]);
        $matching = Report::factory()->create(['reporter_id' => $user->id, 'area_id' => $area->id, 'title' => 'Asap di kebun utara', 'status' => ReportStatus::Ditinjau]);
        $locationMatch = Report::factory()->create(['reporter_id' => $user->id, 'area_id' => $area->id, 'title' => 'Pengamatan kedua', 'location_description' => 'Asap di lahan dekat patokan.', 'status' => ReportStatus::Ditinjau]);
        $foreign = Report::factory()->create(['area_id' => $area->id, 'title' => 'Judul pelapor lain', 'location_description' => 'Asap di lokasi pribadi.', 'status' => ReportStatus::Ditinjau]);
        Report::factory()->create(['reporter_id' => $user->id, 'area_id' => $area->id, 'title' => 'Tanpa kecocokan', 'location_description' => 'Di lokasi berbeda.', 'status' => ReportStatus::Ditinjau]);
        Report::factory()->create(['reporter_id' => $user->id, 'area_id' => $area->id, 'title' => 'Asap status lain', 'status' => ReportStatus::Baru]);
        Report::factory()->create(['reporter_id' => $user->id, 'title' => 'Asap wilayah lain', 'status' => ReportStatus::Ditinjau]);
        $expected = [$locationMatch->id, $matching->id];
        if ($role !== UserRole::Pelapor) {
            array_unshift($expected, $foreign->id);
        }
        $this->actingAs($user)->get(route($route, ['q' => 'Asap', 'area_id' => $area->id, 'status' => 'ditinjau', 'reporter_id' => $foreign->reporter_id]))
            ->assertOk()->assertViewHas('reports', fn ($reports): bool => $reports->pluck('id')->all() === $expected);
    }

    #[DataProvider('roles')]
    public function test_pagination_preserves_validated_filters_and_stable_order(UserRole $role, string $route): void
    {
        $user = User::factory()->create(['role' => $role]);
        $area = Area::factory()->create();
        $own = Report::factory()->count(11)->create(['reporter_id' => $user->id, 'area_id' => $area->id, 'title' => 'Kebun & jalan', 'status' => ReportStatus::Baru, 'created_at' => now()->subDay()]);
        Report::factory()->create(['reporter_id' => $user->id, 'area_id' => $area->id, 'title' => 'Kebun & jalan', 'status' => ReportStatus::Selesai]);
        $filters = ['q' => 'Kebun & jalan', 'area_id' => (string) $area->id, 'status' => 'baru'];
        $response = $this->actingAs($user)->get(route($route, $filters + ['role' => 'koordinator', 'reporter_id' => 999999]))->assertOk();
        $reports = $response->viewData('reports');
        $this->assertSame(11, $reports->total());
        $this->assertSame($own->last()->id, $reports->first()->id);
        parse_str(parse_url($reports->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertEquals($filters + ['page' => '2'], $query);
        $this->get($reports->nextPageUrl())->assertOk()
            ->assertViewHas('reports', fn ($reports): bool => $reports->count() === 1 && $reports->first()->id === $own->first()->id);
    }

    #[DataProvider('roles')]
    public function test_empty_filter_values_mean_all_reports_and_unknown_parameters_are_ignored(UserRole $role, string $route): void
    {
        $user = User::factory()->create(['role' => $role]);
        Report::factory()->create(['reporter_id' => $user->id]);
        $this->actingAs($user)->get(route($route, ['q' => '  ', 'area_id' => '', 'status' => '', 'scope' => 'seluruh_laporan']))
            ->assertOk()->assertViewHas('filters', [])->assertViewHas('reports', fn ($reports): bool => $reports->total() === 1);
    }

    #[DataProvider('statuses')]
    public function test_each_status_can_be_filtered(ReportStatus $status): void
    {
        $user = User::factory()->create();
        foreach (ReportStatus::cases() as $candidate) {
            Report::factory()->create(['reporter_id' => $user->id, 'status' => $candidate]);
        }
        $this->actingAs($user)->get(route('pelapor.reports.index', ['status' => $status->value]))->assertOk()
            ->assertViewHas('reports', fn ($reports): bool => $reports->total() === 1 && $reports->first()->status === $status);
    }

    #[DataProvider('literalSearches')]
    public function test_search_treats_sql_wildcards_and_quotes_as_literal_text(string $term): void
    {
        $user = User::factory()->create();
        $matching = Report::factory()->create(['reporter_id' => $user->id, 'title' => 'Patokan '.$term.' di kebun']);
        Report::factory()->create(['reporter_id' => $user->id, 'title' => 'Laporan lain tanpa tanda']);
        $this->actingAs($user)->get(route('pelapor.reports.index', ['q' => $term]))->assertOk()
            ->assertViewHas('reports', fn ($reports): bool => $reports->total() === 1 && $reports->first()->id === $matching->id);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected_without_modifying_reports(string $field, mixed $value): void
    {
        $report = Report::factory()->create();
        $before = $report->fresh()->toArray();
        $this->actingAs($report->reporter)->getJson(route('pelapor.reports.index', [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertEquals($before, $report->fresh()->toArray());
    }

    public function test_html_validation_redirects_to_clean_list_instead_of_repeating_invalid_query(): void
    {
        $url = route('pelapor.reports.index');
        $this->actingAs(User::factory()->create())->from($url.'?status=salah')->get($url.'?status=salah')
            ->assertRedirect($url)->assertSessionHasErrors('status');
        $this->withCookie(config('session.cookie'), session()->getId())->get($url)->assertOk();
    }

    public static function roles(): array
    {
        return [[UserRole::Pelapor, 'pelapor.reports.index'], [UserRole::Relawan, 'relawan.reports.index'], [UserRole::Koordinator, 'koordinator.reports.index']];
    }

    public static function statuses(): array
    {
        return array_map(fn (ReportStatus $status): array => [$status], ReportStatus::cases());
    }

    public static function literalSearches(): array
    {
        return [['%'], ['_'], ['!'], ["' OR 1=1 --"], ['100%_!']];
    }

    public static function invalidFilters(): array
    {
        return [['q', ['salah']], ['q', str_repeat('A', 151)], ['area_id', ['salah']], ['area_id', 'abc'], ['area_id', 999999], ['status', ['baru']], ['status', 'salah'], ['page', 0], ['page', -1], ['page', 'abc'], ['page', ['salah']], ['page', 2147483648]];
    }
}
