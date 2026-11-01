<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportRecapViewTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('staffRoles')]
    public function test_filtered_view_displays_matching_cards_table_and_selected_filter(UserRole $role): void
    {
        $area = Area::factory()->create(['name' => '<b>Wilayah uji</b>']);
        Report::factory()->count(2)->create(['area_id' => $area->id]);
        Report::factory()->create(['area_id' => $area->id, 'status' => ReportStatus::Selesai]);
        Report::factory()->count(2)->create(['status' => ReportStatus::Selesai]);
        $response = $this->actingAs(User::factory()->create(['role' => $role]))->get(route('reports.recap', ['area_id' => $area->id]))
            ->assertOk()->assertSee('&lt;b&gt;Wilayah uji&lt;/b&gt;', false)->assertDontSee('<b>Wilayah uji</b>', false);
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        foreach (['//section[@aria-label="Ringkasan laporan"]//dd', '//table/tbody/tr/td', '//table/tfoot/tr/td'] as $selector) {
            $values = [];
            foreach ($xpath->query($selector) as $node) {
                $values[] = trim($node->textContent);
            }
            $this->assertSame(['3', '2', '1'], $values);
        }
        $this->assertSame((string) $area->id, $xpath->evaluate('string(//select[@name="area_id"]/option[@selected]/@value)'));
        $this->assertSame(route('reports.recap'), $xpath->evaluate('string(//a[normalize-space(.)="Reset filter"]/@href)'));
        $this->get(route('reports.recap'))->assertOk()->assertViewHas('summary', ['total' => 5, 'active' => 2, 'completed' => 3]);
    }

    #[DataProvider('allRoles')]
    public function test_dashboard_only_offers_recap_to_authorized_roles(UserRole $role): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => $role]))->get(route($role->value.'.dashboard'))->assertOk();
        if ($role === UserRole::Pelapor) {
            $response->assertDontSee(route('reports.recap'), false);
        } else {
            $response->assertSee(route('reports.recap'), false)->assertSee('Rekap laporan');
            $this->get(route($role->value.'.reports.index'))->assertOk()->assertSee('Lihat rekap laporan');
        }
    }

    public function test_invalid_html_filter_returns_to_clean_recap_with_indonesian_error(): void
    {
        $url = route('reports.recap');
        $this->actingAs(User::factory()->relawan()->create())->from($url.'?area_id=invalid')->get($url.'?area_id=invalid')
            ->assertRedirect($url)->assertSessionHasErrors('area_id');
        $this->withCookie(config('session.cookie'), session()->getId())->get($url)
            ->assertOk()->assertSee('Filter belum diterapkan.')->assertSee('Pilihan wilayah tidak valid.');
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
