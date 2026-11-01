<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MiniProjectAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ac_01_to_ac_13_work_together_on_seeded_demo_data(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $owner = User::query()->where('email', 'pelapor@example.test')->firstOrFail();
        $other = User::query()->where('email', 'pelapor2@example.test')->firstOrFail();
        $volunteer = User::query()->where('email', 'relawan@example.test')->firstOrFail();
        $coordinator = User::query()->where('email', 'koordinator@example.test')->firstOrFail();
        $area = Area::query()->where('code', 'DEMO-A')->firstOrFail();
        $inactive = Area::query()->where('code', 'DEMO-LAMA')->firstOrFail();

        $this->getJson(route('pelapor.reports.index'))->assertUnauthorized();
        $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'KarhutlaDemo123!'])->assertRedirect();
        $this->assertAuthenticatedAs($owner);
        $input = [
            'title' => 'SIMULASI pengujian terpadu seluruh alur',
            'description' => 'Data simulasi untuk pengujian terpadu mini project, bukan kejadian kebakaran nyata.',
            'area_id' => $area->id, 'occurred_at' => now()->subHour()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'location_description' => 'Lokasi simulasi di utara pos warga wilayah demo.', 'latitude' => '-90', 'longitude' => '180',
        ];
        $filesBefore = Storage::disk('local')->allFiles();
        $this->postJson(route('pelapor.reports.store'), array_replace($input, ['latitude' => 91]))->assertUnprocessable();
        $this->postJson(route('pelapor.reports.store'), $input + ['evidence' => UploadedFile::fake()->create('bukti.exe', 1)])->assertUnprocessable();
        $this->postJson(route('pelapor.reports.store'), array_replace($input, ['area_id' => $inactive->id]))->assertUnprocessable();
        $this->assertDatabaseCount('reports', 26);
        $this->assertSame($filesBefore, Storage::disk('local')->allFiles());

        $this->post(route('pelapor.reports.store'), $input + [
            'reporter_id' => $other->id, 'status' => 'selesai',
            'evidence' => new UploadedFile(database_path('seeders/fixtures/bukti-simulasi.png'), 'bukti-simulasi.png', null, UPLOAD_ERR_OK, true),
        ])->assertRedirect();
        $report = Report::query()->where('title', $input['title'])->sole();
        $this->assertSame($owner->id, $report->reporter_id);
        $this->assertSame(ReportStatus::Baru, $report->status);
        $this->get(route('reports.evidence', $report))->assertOk()->assertHeader('Content-Type', 'image/png');
        $before = $report->fresh()->toArray();
        $this->actingAs($other)->get(route('pelapor.reports.show', $report))->assertNotFound();
        $this->get(route('pelapor.reports.edit', $report))->assertNotFound();
        $this->patchJson(route('pelapor.reports.update', $report), $input)->assertNotFound();
        $this->get(route('reports.evidence', $report))->assertNotFound();
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->postJson(route('relawan.reports.start-review', $report))->assertForbidden();

        $this->actingAs($volunteer)->post(route('relawan.reports.start-review', $report))->assertRedirect();
        $this->postJson(route('relawan.reports.start-review', $report))->assertStatus(409);
        $this->postJson(route('relawan.reports.review', $report), ['decision' => 'perlu_perbaikan', 'note' => ''])->assertUnprocessable();
        $this->post(route('relawan.reports.review', $report), ['decision' => 'perlu_perbaikan', 'note' => 'SIMULASI: mohon perjelas deskripsi kondisi yang diamati.'])->assertRedirect();
        $this->actingAs($owner)->patch(route('pelapor.reports.update', $report), array_replace($input, ['description' => 'SIMULASI perbaikan: asap tipis dicontohkan dari lahan kering di utara pos warga, bukan kejadian nyata.']))->assertRedirect();
        $this->assertSame(2, $report->statusLogs()->count());
        $this->post(route('pelapor.reports.resubmit', $report))->assertRedirect();
        $this->patchJson(route('pelapor.reports.update', $report), $input)->assertStatus(409);
        $this->actingAs($volunteer)->post(route('relawan.reports.review', $report), ['decision' => 'perlu_diteruskan', 'note' => 'SIMULASI: informasi cukup untuk dicatat penerusannya.'])->assertRedirect();
        $forwarding = ['recipient' => 'Posko simulasi', 'forwarded_at' => now()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i:s'), 'note' => 'SIMULASI: informasi diteruskan kepada posko contoh.'];
        $this->postJson(route('koordinator.reports.forward', $report), $forwarding)->assertForbidden();
        $this->actingAs($coordinator)->post(route('koordinator.reports.forward', $report), $forwarding)->assertRedirect();
        $this->postJson(route('koordinator.reports.forward', $report), $forwarding)->assertStatus(409);
        $this->post(route('koordinator.reports.close', $report), ['closure_note' => 'SIMULASI: pencatatan penerusan sudah selesai.'])->assertRedirect();
        $this->postJson(route('koordinator.reports.close', $report), ['closure_note' => 'Percobaan menutup ulang laporan simulasi.'])->assertStatus(409);
        $this->deleteJson(route('koordinator.areas.destroy', $inactive))->assertUnprocessable()->assertJsonValidationErrors('area');
        $this->assertSame(ReportStatus::Selesai, $report->fresh()->status);
        $this->assertSame(6, $report->statusLogs()->count());
        $this->assertSame(1, $report->forwarding()->count());
        $this->get(route('reports.recap', ['area_id' => $area->id]))->assertOk()
            ->assertViewHas('summary', ['total' => 14, 'active' => 12, 'completed' => 2]);

        $this->actingAs($owner)->getJson(route('areas.summary', $area))->assertOk()
            ->assertJsonPath('data.active_report_count', 11)->assertJsonPath('data.scope', 'milik_sendiri');
        $this->getJson(route('reports.recap'))->assertForbidden();
        $response = $this->get(route('pelapor.reports.index', ['q' => 'SIMULASI asap', 'area_id' => $area->id, 'status' => 'baru']))
            ->assertOk()->assertViewHas('reports', fn ($reports): bool => $reports->total() === 11 && $reports->count() === 10);
        $nextPage = $response->viewData('reports')->nextPageUrl();
        $this->assertStringContainsString('status=baru', $nextPage);
        $this->assertStringContainsString('area_id='.$area->id, $nextPage);
        $this->get($nextPage)->assertOk()->assertViewHas('reports', fn ($reports): bool => $reports->count() === 1);
        $this->get(route('pelapor.reports.show', $report))->assertOk()->assertSee('Riwayat perubahan status')->assertSee('Ditinjau → Perlu diteruskan');
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
