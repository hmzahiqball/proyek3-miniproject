<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportForwarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoordinatorForwardingViewTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('statuses')]
    public function test_form_is_only_shown_on_eligible_status(ReportStatus $status): void
    {
        $report = Report::factory()->create(['status' => $status]);
        $response = $this->actingAs(User::factory()->koordinator()->create())->get(route('koordinator.reports.show', $report))->assertOk();
        if ($status === ReportStatus::PerluDiteruskan) {
            $response->assertSee('Simpan penerusan')->assertSee('Tujuan penerusan')->assertSee('Waktu penerusan (WIB)')
                ->assertSee('step="1"', false)->assertDontSee('Tutup laporan');
        } else {
            $response->assertDontSee('Simpan penerusan');
        }
    }

    public function test_saved_forwarding_replaces_form_and_escapes_record_content(): void
    {
        $this->travelTo(now()->startOfSecond());
        $report = Report::factory()->create(['status' => ReportStatus::PerluDiteruskan, 'created_at' => now()->subHour()]);
        $coordinator = User::factory()->koordinator()->create();
        $this->actingAs($coordinator)->post(route('koordinator.reports.forward', $report), [
            'recipient' => '<script>tujuan</script>', 'note' => '<script>catatan simulasi</script>',
            'forwarded_at' => now()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i:s'),
        ])->assertRedirect();
        $this->get(route('koordinator.reports.show', $report))->assertOk()
            ->assertSee('&lt;script&gt;tujuan&lt;/script&gt;', false)->assertDontSee('<script>tujuan</script>', false)
            ->assertSee('&lt;script&gt;catatan simulasi&lt;/script&gt;', false)->assertDontSee('<script>catatan simulasi</script>', false)
            ->assertSee(now()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB')
            ->assertSee($coordinator->name)->assertSee('Tutup laporan')->assertDontSee('Simpan penerusan');
    }

    public function test_existing_forwarding_hides_form_even_if_report_status_is_inconsistent(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::PerluDiteruskan]);
        $forwarding = ReportForwarding::factory()->create(['report_id' => $report->id]);
        $this->actingAs(User::factory()->koordinator()->create())->get(route('koordinator.reports.show', $report))
            ->assertOk()->assertSee($forwarding->recipient)->assertDontSee('Simpan penerusan');
    }

    public function test_validation_redirect_keeps_safe_input_and_shows_indonesian_error(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::PerluDiteruskan]);
        $url = route('koordinator.reports.show', $report);
        $this->actingAs(User::factory()->koordinator()->create())->from($url)->post(route('koordinator.reports.forward', $report), [
            'recipient' => '<b>Posko simulasi</b>', 'note' => 'pendek', 'forwarded_at' => 'bukan waktu',
        ])->assertRedirect($url)->assertSessionHasErrors(['note', 'forwarded_at']);
        $this->withCookie(config('session.cookie'), session()->getId())->get($url)->assertOk()
            ->assertSee('Perubahan belum disimpan.')->assertSee('Catatan penerusan minimal 10 karakter.')
            ->assertSee('&lt;b&gt;Posko simulasi&lt;/b&gt;', false)->assertDontSee('<b>Posko simulasi</b>', false)
            ->assertSee('pendek');
        $this->assertDatabaseCount('report_forwardings', 0);
    }

    public static function statuses(): array
    {
        return array_map(fn (ReportStatus $status): array => [$status], ReportStatus::cases());
    }
}
