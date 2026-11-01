<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('editableStatuses')]
    public function test_owner_can_edit_allowed_status_without_changing_owner_status_or_evidence(ReportStatus $status): void
    {
        $report = Report::factory()->create(['status' => $status, 'evidence_path' => 'private/bukti.pdf', 'evidence_mime' => 'application/pdf', 'evidence_size' => 100]);
        $other = User::factory()->create();
        $this->actingAs($report->reporter)->get(route('pelapor.reports.edit', $report))->assertOk()
            ->assertSee($report->title)->assertSee('Gunakan GPS')->assertSee('Simpan perbaikan');
        $input = $this->validInput($report);
        $this->patch(route('pelapor.reports.update', $report), [
            ...$input, 'reporter_id' => $other->id, 'status' => 'selesai', 'role' => 'koordinator',
            'evidence_path' => 'palsu.pdf', 'evidence_mime' => 'palsu', 'evidence_size' => 999,
            'closure_note' => 'Ditutup paksa', 'closed_at' => now()->toDateTimeString(),
        ])->assertRedirect(route('pelapor.reports.show', $report))->assertSessionHasNoErrors()->assertSessionHas('status');

        $fresh = $report->fresh();
        $this->assertSame($input['title'], $fresh->title);
        $this->assertSame($input['description'], $fresh->description);
        $this->assertSame($input['location_description'], $fresh->location_description);
        $this->assertSame('-7.1000001', $fresh->latitude);
        $this->assertSame('108.1000001', $fresh->longitude);
        $this->assertSame('2026-10-31 03:00:00', $fresh->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame($report->reporter_id, $fresh->reporter_id);
        $this->assertSame($status, $fresh->status);
        $this->assertSame('private/bukti.pdf', $fresh->evidence_path);
        $this->assertSame('application/pdf', $fresh->evidence_mime);
        $this->assertSame(100, $fresh->evidence_size);
        $this->assertNull($fresh->closure_note);
        $this->assertNull($fresh->closed_at);
        $this->assertSame($report->created_at->toDateTimeString(), $fresh->created_at->toDateTimeString());
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('report_reviews', 0);
        $this->assertDatabaseCount('report_status_logs', 0);
    }

    #[DataProvider('lockedStatuses')]
    public function test_locked_status_blocks_form_and_update_without_mutation(ReportStatus $status): void
    {
        $report = Report::factory()->create(['status' => $status]);
        $before = $report->fresh()->toArray();
        $this->actingAs($report->reporter)->get(route('pelapor.reports.edit', $report))->assertStatus(409);
        $this->patchJson(route('pelapor.reports.update', $report), $this->validInput($report))->assertStatus(409);
        $this->assertEquals($before, $report->fresh()->toArray());
    }

    public function test_foreign_and_missing_ids_are_rejected_before_validation(): void
    {
        $report = Report::factory()->create();
        $before = $report->fresh()->toArray();
        $this->actingAs(User::factory()->create());
        foreach ([$report->id, 999999] as $id) {
            $this->get(route('pelapor.reports.edit', $id))->assertNotFound()->assertDontSee($report->title);
            $this->patchJson(route('pelapor.reports.update', $id), [])->assertNotFound()->assertDontSee($report->title);
        }
        $this->assertEquals($before, $report->fresh()->toArray());
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_non_reporter_or_inactive_accounts_cannot_open_or_submit_edit(?UserRole $role, bool $active): void
    {
        $report = Report::factory()->create();
        $before = $report->fresh()->toArray();
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $status = $role === null || ! $active ? 401 : 403;
        $this->getJson(route('pelapor.reports.edit', $report))->assertStatus($status);
        $this->patchJson(route('pelapor.reports.update', $report), [...$this->validInput($report), 'role' => 'pelapor'])->assertStatus($status);
        $this->assertEquals($before, $report->fresh()->toArray());
    }

    public function test_same_inactive_area_is_allowed_and_is_the_only_inactive_option(): void
    {
        $report = Report::factory()->create(['area_id' => Area::factory()->create(['is_active' => false, 'name' => 'Wilayah lama nonaktif'])->id]);
        $other = Area::factory()->create(['is_active' => false, 'name' => 'Wilayah nonaktif lain']);
        $this->actingAs($report->reporter)->get(route('pelapor.reports.edit', $report))->assertOk()
            ->assertSee('Wilayah lama nonaktif')->assertDontSee($other->name);
        $this->patch(route('pelapor.reports.update', $report), $this->validInput($report))
            ->assertRedirect(route('pelapor.reports.show', $report))->assertSessionHasNoErrors();
        $this->assertSame($report->area_id, $report->fresh()->area_id);
    }

    public function test_replacement_must_be_active_and_put_method_is_supported(): void
    {
        $report = Report::factory()->create();
        $inactive = Area::factory()->create(['is_active' => false]);
        $active = Area::factory()->create();
        $before = $report->fresh()->toArray();
        $this->actingAs($report->reporter)->putJson(route('pelapor.reports.update', $report), [
            ...$this->validInput($report), 'area_id' => $inactive->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('area_id');
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->put(route('pelapor.reports.update', $report), [...$this->validInput($report), 'area_id' => $active->id])
            ->assertRedirect(route('pelapor.reports.show', $report));
        $this->assertSame($active->id, $report->fresh()->area_id);
    }

    public function test_status_changed_after_validation_is_checked_again_in_transaction(): void
    {
        $report = Report::factory()->create();
        $this->app->afterResolving(UpdateReportRequest::class, function () use ($report): void {
            $report->status = ReportStatus::Ditinjau;
            $report->save();
        });
        $this->actingAs($report->reporter)->patchJson(route('pelapor.reports.update', $report), $this->validInput($report))->assertStatus(409);
        $fresh = $report->fresh();
        $this->assertSame(ReportStatus::Ditinjau, $fresh->status);
        $this->assertSame($report->title, $fresh->title);
        $this->assertSame($report->latitude, $fresh->latitude);
    }

    public function test_area_deactivated_after_validation_cannot_be_used_as_replacement(): void
    {
        $report = Report::factory()->create();
        $area = Area::factory()->create();
        $before = $report->fresh()->toArray();
        $this->app->afterResolving(UpdateReportRequest::class, function () use ($area): void {
            $area->update(['is_active' => false]);
        });
        $this->actingAs($report->reporter)->patchJson(route('pelapor.reports.update', $report), [
            ...$this->validInput($report), 'area_id' => $area->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('area_id');
        $this->assertEquals($before, $report->fresh()->toArray());
        $this->assertFalse($area->fresh()->is_active);
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_changes_are_rejected_atomically(string $field, mixed $value): void
    {
        $report = Report::factory()->create();
        $before = $report->fresh()->toArray();
        $this->actingAs($report->reporter)->patchJson(route('pelapor.reports.update', $report), [
            ...$this->validInput($report), $field => $value,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertEquals($before, $report->fresh()->toArray());
    }

    public function test_invalid_html_input_is_preserved_safely_and_form_uses_local_time(): void
    {
        $report = Report::factory()->create(['occurred_at' => '2026-10-31 03:00:00']);
        $this->actingAs($report->reporter)->get(route('pelapor.reports.edit', $report))->assertOk()->assertSee('2026-10-31T10:00');
        $this->from(route('pelapor.reports.edit', $report))->patch(route('pelapor.reports.update', $report), [
            ...$this->validInput($report), 'latitude' => ['salah'],
        ])->assertRedirect(route('pelapor.reports.edit', $report))->assertSessionHasErrors('latitude');
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('pelapor.reports.edit', $report))
            ->assertOk()->assertSee('Perbaikan belum disimpan.')->assertSee('Asap di lahan sebelah jalan');
    }

    #[DataProvider('allStatuses')]
    public function test_update_policy_and_detail_button_match_allowed_statuses(ReportStatus $status): void
    {
        $report = Report::factory()->create(['status' => $status]);
        $editable = in_array($status, [ReportStatus::Baru, ReportStatus::PerluPerbaikan], true);
        $this->assertSame($editable, Gate::forUser($report->reporter)->allows('update', $report));
        $response = $this->actingAs($report->reporter)->get(route('pelapor.reports.show', $report))->assertOk();
        if ($editable) {
            $response->assertSee('Perbaiki laporan');
        } else {
            $response->assertDontSee('Perbaiki laporan');
        }
    }

    /** @return array<string, mixed> */
    private function validInput(Report $report): array
    {
        return [
            'title' => 'Asap di lahan sebelah jalan', 'description' => 'Kondisi diperbaiki: asap tipis terlihat di sisi timur jalan utama.',
            'occurred_at' => '2026-10-31T10:00', 'area_id' => $report->area_id,
            'location_description' => 'Lokasi diperbaiki: lahan sebelah timur pos warga.',
            'latitude' => '-7.1000001', 'longitude' => '108.1000001',
        ];
    }

    public static function editableStatuses(): array
    {
        return [[ReportStatus::Baru], [ReportStatus::PerluPerbaikan]];
    }

    public static function lockedStatuses(): array
    {
        return [[ReportStatus::Ditinjau], [ReportStatus::PerluDiteruskan], [ReportStatus::Diteruskan], [ReportStatus::Selesai]];
    }

    public static function allStatuses(): array
    {
        return array_map(fn (ReportStatus $status): array => [$status], ReportStatus::cases());
    }

    public static function unauthorizedUsers(): array
    {
        return [[null, true], [UserRole::Relawan, true], [UserRole::Koordinator, true], [UserRole::Pelapor, false]];
    }

    public static function invalidInput(): array
    {
        return [
            ['title', 'Asap'], ['description', str_repeat('A', 5001)], ['location_description', 'Pendek'],
            ['latitude', 91], ['longitude', -181], ['occurred_at', '9999-01-01T00:00'],
            ['area_id', 999999], ['latitude', ['salah']],
        ];
    }
}
