<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportFilterViewTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_empty_results_keep_selected_filters_and_offer_a_clean_reset(UserRole $role, string $route): void
    {
        $area = Area::factory()->create(['is_active' => false]);
        $url = route($route);
        $response = $this->actingAs(User::factory()->create(['role' => $role]))->get(route($route, [
            'q' => '<script>alert(1)</script>', 'area_id' => $area->id, 'status' => 'baru', 'page' => 3,
        ]))->assertOk()->assertSee('Tidak ada laporan yang sesuai pencarian dan filter.')
            ->assertSee('0 laporan sesuai pencarian dan filter.')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee($area->name.' (nonaktif)');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame((string) $area->id, $xpath->evaluate('string(//select[@name="area_id"]/option[@selected]/@value)'));
        $this->assertSame('baru', $xpath->evaluate('string(//select[@name="status"]/option[@selected]/@value)'));
        $this->assertSame($url, $xpath->evaluate('string(//a[normalize-space(.)="Reset filter"]/@href)'));
        $this->assertSame($url, $xpath->evaluate('string(//form[@aria-label="Pencarian dan filter laporan"]/@action)'));
        $this->assertSame(0, $xpath->query('//form[@method="GET"]//input[@name="page"]')->length);
        $this->get($url)->assertOk()->assertViewHas('filters', []);
    }

    #[DataProvider('roles')]
    public function test_out_of_range_page_links_to_first_page_with_filters_preserved(UserRole $role, string $route): void
    {
        $user = User::factory()->create(['role' => $role]);
        $report = Report::factory()->create(['reporter_id' => $user->id, 'title' => 'Asap kebun utara']);
        $response = $this->actingAs($user)->get(route($route, ['q' => 'Asap', 'area_id' => $report->area_id, 'status' => 'baru', 'page' => 5]))
            ->assertOk()->assertSee('Tidak ada laporan pada halaman ini.')->assertSee('Kembali ke halaman pertama');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $firstPageUrl = $xpath->evaluate('string(//a[normalize-space(.)="Kembali ke halaman pertama"]/@href)');
        $this->get($firstPageUrl)->assertOk()->assertSee($report->title)
            ->assertViewHas('filters', ['q' => 'Asap', 'area_id' => (string) $report->area_id, 'status' => 'baru']);
    }

    public function test_invalid_html_filter_shows_indonesian_validation_message(): void
    {
        $url = route('pelapor.reports.index');
        $this->actingAs(User::factory()->create())->get($url.'?status=salah')->assertRedirect($url);
        $this->withCookie(config('session.cookie'), session()->getId())->get($url)
            ->assertOk()->assertSee('Filter belum diterapkan.')->assertSee('Status laporan tidak valid.');
    }

    public static function roles(): array
    {
        return [[UserRole::Pelapor, 'pelapor.reports.index'], [UserRole::Relawan, 'relawan.reports.index'], [UserRole::Koordinator, 'koordinator.reports.index']];
    }
}
