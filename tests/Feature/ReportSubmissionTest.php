<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreReportRequest;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporter_can_submit_event_and_location_with_wib_converted_to_utc(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(8, 30));
        $reporter = User::factory()->create();
        $area = Area::factory()->create();
        $input = $this->validInput($area);
        $input['occurred_at'] = '2026-11-01T15:29';

        $this->actingAs($reporter)->post(route('pelapor.reports.store'), $input)
            ->assertRedirect(route('pelapor.dashboard'))->assertSessionHas('status', 'Laporan berhasil dikirim dengan status Baru.');

        $report = Report::sole();
        $this->assertSame($reporter->id, $report->reporter_id);
        $this->assertSame($area->id, $report->area_id);
        $this->assertSame($input['title'], $report->title);
        $this->assertSame($input['description'], $report->description);
        $this->assertSame($input['location_description'], $report->location_description);
        $this->assertSame('2026-11-01 08:29:00', $report->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame('-6.9000001', $report->latitude);
        $this->assertSame('107.6000001', $report->longitude);
        $this->assertSame(ReportStatus::Baru, $report->status);
        $this->assertNull($report->evidence_path);
        $this->assertNull($report->closed_at);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_client_cannot_choose_owner_status_or_evidence_metadata(): void
    {
        $reporter = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($reporter)->post(route('pelapor.reports.store'), [
            ...$this->validInput(Area::factory()->create()),
            'id' => 9999, 'reporter_id' => $other->id, 'role' => 'koordinator', 'status' => 'selesai',
            'evidence_path' => 'private/palsu.pdf', 'evidence_mime' => 'application/pdf', 'evidence_size' => 100,
            'closed_at' => now()->toDateTimeString(), 'closure_note' => 'Ditutup paksa',
        ])->assertRedirect(route('pelapor.dashboard'));

        $report = Report::sole();
        $this->assertSame($reporter->id, $report->reporter_id);
        $this->assertNotSame(9999, $report->id);
        $this->assertSame(ReportStatus::Baru, $report->status);
        $this->assertNull($report->evidence_path);
        $this->assertNull($report->evidence_mime);
        $this->assertNull($report->evidence_size);
        $this->assertNull($report->closed_at);
        $this->assertNull($report->closure_note);
        $this->assertSame(UserRole::Pelapor, $reporter->fresh()->role);
    }

    #[DataProvider('coordinateBoundaries')]
    public function test_coordinate_boundaries_are_accepted(string $latitude, string $longitude): void
    {
        $this->actingAs(User::factory()->create())->post(route('pelapor.reports.store'), [
            ...$this->validInput(Area::factory()->create()), 'latitude' => $latitude, 'longitude' => $longitude,
        ])->assertRedirect(route('pelapor.dashboard'))->assertSessionHasNoErrors();
        $this->assertSame((float) $latitude, (float) Report::sole()->latitude);
        $this->assertSame((float) $longitude, (float) Report::sole()->longitude);
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_fields_are_rejected_without_creating_a_report(string $field, mixed $value): void
    {
        $input = $this->validInput(Area::factory()->create());
        $input[$field] = $value;
        $this->actingAs(User::factory()->create())->postJson(route('pelapor.reports.store'), $input)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_all_fields_are_required(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('pelapor.reports.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors([
                'title', 'description', 'occurred_at', 'area_id', 'location_description', 'latitude', 'longitude',
            ]);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_future_wib_time_is_rejected_and_current_minute_is_accepted(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(8, 30));
        $input = $this->validInput(Area::factory()->create());
        $this->actingAs(User::factory()->create())->postJson(route('pelapor.reports.store'), [
            ...$input, 'occurred_at' => '2026-11-01T15:31',
        ])->assertUnprocessable()->assertJsonValidationErrors('occurred_at');
        $this->assertDatabaseCount('reports', 0);
        $this->post(route('pelapor.reports.store'), [...$input, 'occurred_at' => '2026-11-01T15:30'])
            ->assertRedirect(route('pelapor.dashboard'));
        $this->assertSame('2026-11-01 08:30:00', Report::sole()->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_wib_conversion_handles_a_different_utc_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(20, 0));
        $this->actingAs(User::factory()->create())->post(route('pelapor.reports.store'), [
            ...$this->validInput(Area::factory()->create()), 'occurred_at' => '2026-11-02T01:00',
        ])->assertRedirect(route('pelapor.dashboard'));
        $this->assertSame('2026-11-01 18:00:00', Report::sole()->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_text_length_boundaries_are_accepted(): void
    {
        $input = $this->validInput(Area::factory()->create());
        $this->actingAs(User::factory()->create());
        foreach ([[5, 20, 10], [150, 5000, 500]] as [$title, $description, $location]) {
            $this->post(route('pelapor.reports.store'), [
                ...$input, 'title' => str_repeat('A', $title), 'description' => str_repeat('B', $description),
                'location_description' => str_repeat('C', $location),
            ])->assertRedirect(route('pelapor.dashboard'))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('reports', 2);
    }

    public function test_inactive_area_is_hidden_and_rejected_on_direct_submission(): void
    {
        $inactive = Area::factory()->create(['is_active' => false, 'name' => 'Wilayah nonaktif tersembunyi']);
        $active = Area::factory()->create(['name' => 'Wilayah aktif tersedia']);
        $this->actingAs(User::factory()->create())->get(route('pelapor.reports.create'))
            ->assertOk()->assertSee($active->name)->assertDontSee($inactive->name);
        $this->postJson(route('pelapor.reports.store'), $this->validInput($inactive))
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_area_deactivated_after_validation_is_checked_again_before_saving(): void
    {
        $area = Area::factory()->create();
        $this->app->afterResolving(StoreReportRequest::class, function () use ($area): void {
            $area->update(['is_active' => false]);
        });
        $this->actingAs(User::factory()->create())->postJson(route('pelapor.reports.store'), $this->validInput($area))
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');
        $this->assertDatabaseCount('reports', 0);
        $this->assertFalse($area->fresh()->is_active);
    }

    public function test_empty_active_area_list_disables_submission_and_explains_next_step(): void
    {
        $this->actingAs(User::factory()->create())->get(route('pelapor.reports.create'))
            ->assertOk()->assertSee('Belum ada wilayah aktif.')->assertSee('disabled', false);
    }

    public function test_validation_redirect_preserves_input_and_shows_indonesian_errors(): void
    {
        $input = $this->validInput(Area::factory()->create());
        $input['latitude'] = '91';
        $this->actingAs(User::factory()->create())->from(route('pelapor.reports.create'))
            ->post(route('pelapor.reports.store'), $input)->assertRedirect(route('pelapor.reports.create'))
            ->assertSessionHasErrors('latitude')->assertSessionHasInput('title', $input['title']);
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('pelapor.reports.create'))
            ->assertOk()->assertSee('Lintang harus berada antara -90 dan 90.')->assertSee($input['title']);
        $this->assertDatabaseCount('reports', 0);
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_unauthorized_requests_cannot_open_form_or_create_report(?UserRole $role, bool $active): void
    {
        $input = $this->validInput(Area::factory()->create());
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $status = $role === null || ! $active ? 401 : 403;
        $this->getJson(route('pelapor.reports.create'))->assertStatus($status);
        $this->postJson(route('pelapor.reports.store'), [...$input, 'role' => 'pelapor'])->assertStatus($status);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_html_form_can_redisplay_errors_when_fields_contain_arrays(): void
    {
        $input = array_fill_keys(array_keys($this->validInput(Area::factory()->create())), ['invalid']);
        $this->actingAs(User::factory()->create())->from(route('pelapor.reports.create'))
            ->post(route('pelapor.reports.store'), $input)->assertRedirect(route('pelapor.reports.create'))
            ->assertSessionHasErrors(array_keys($input));
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('pelapor.reports.create'))
            ->assertOk()->assertSee('Laporan belum terkirim.')->assertSee('Ringkasan kejadian harus berupa teks.');
        $this->assertDatabaseCount('reports', 0);
    }

    /** @return array<string, mixed> */
    private function validInput(Area $area): array
    {
        return [
            'title' => 'Asap terlihat di tepi jalan',
            'description' => 'Asap tipis terlihat dari lahan kering di sebelah timur jalan utama.',
            'occurred_at' => now('Asia/Jakarta')->subHour()->format('Y-m-d\TH:i'),
            'area_id' => $area->id,
            'location_description' => 'Lahan di sebelah timur jalan utama dekat pos warga.',
            'latitude' => '-6.9000001', 'longitude' => '107.6000001',
        ];
    }

    public static function coordinateBoundaries(): array
    {
        return [['-90', '-180'], ['90', '180'], ['0', '0']];
    }

    public static function unauthorizedUsers(): array
    {
        return [
            'guest' => [null, true], 'relawan' => [UserRole::Relawan, true],
            'koordinator' => [UserRole::Koordinator, true], 'pelapor nonaktif' => [UserRole::Pelapor, false],
        ];
    }

    public static function invalidInput(): array
    {
        return [
            'judul pendek' => ['title', 'Asap'], 'judul panjang' => ['title', str_repeat('A', 151)],
            'judul array' => ['title', ['A']], 'judul kosong' => ['title', ' '],
            'kondisi pendek' => ['description', 'Asap tipis'], 'kondisi panjang' => ['description', str_repeat('A', 5001)],
            'kondisi array' => ['description', ['A']],
            'lokasi pendek' => ['location_description', 'Di jalan'], 'lokasi panjang' => ['location_description', str_repeat('A', 501)],
            'lokasi array' => ['location_description', ['A']],
            'waktu tidak valid' => ['occurred_at', '2026-02-30T10:00'], 'waktu array' => ['occurred_at', ['A']],
            'waktu format bebas' => ['occurred_at', 'besok'], 'waktu di luar penyimpanan' => ['occurred_at', '0001-01-01T10:00'],
            'wilayah tidak ada' => ['area_id', 999999], 'wilayah bukan id' => ['area_id', 'wilayah'],
            'wilayah array' => ['area_id', [1]],
            'lintang di bawah batas' => ['latitude', '-90.0000001'], 'lintang di atas batas' => ['latitude', '90.0000001'],
            'bujur di bawah batas' => ['longitude', '-180.0000001'], 'bujur di atas batas' => ['longitude', '180.0000001'],
            'lintang teks' => ['latitude', 'abc'], 'bujur teks' => ['longitude', 'abc'],
            'lintang array' => ['latitude', [0]], 'bujur array' => ['longitude', [0]],
            'lintang koma' => ['latitude', '-6,9'], 'bujur koma' => ['longitude', '107,6'],
            'lintang tidak hingga' => ['latitude', 'INF'], 'bujur bukan angka' => ['longitude', 'NaN'],
        ];
    }
}
