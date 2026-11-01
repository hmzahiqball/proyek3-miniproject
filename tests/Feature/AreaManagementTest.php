<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AreaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_open_pages_and_create_a_normalized_area(): void
    {
        $this->actingAs(User::factory()->koordinator()->create());
        $this->get(route('koordinator.areas.index'))->assertOk()->assertSee('Belum ada wilayah');
        $this->get(route('koordinator.areas.create'))->assertOk();
        $this->post(route('koordinator.areas.store'), [
            'code' => ' wil-01 ', 'name' => 'Wilayah Utara', 'is_active' => '1', 'id' => 9999,
        ])->assertRedirect(route('koordinator.areas.index'))->assertSessionHas('status');

        $area = Area::sole();
        $this->assertSame('WIL-01', $area->code);
        $this->assertTrue($area->is_active);
        $this->assertNotSame(9999, $area->id);
        $this->get(route('koordinator.areas.edit', $area))->assertOk()->assertSee('Wilayah Utara');
    }

    public function test_coordinator_can_rename_deactivate_and_reactivate_a_referenced_area(): void
    {
        $report = Report::factory()->create();
        $area = $report->area;
        $this->actingAs(User::factory()->koordinator()->create());

        foreach ([false, true] as $active) {
            $this->put(route('koordinator.areas.update', $area), [
                'name' => 'Nama diperbaiki', 'is_active' => $active,
            ])->assertRedirect(route('koordinator.areas.index'))->assertSessionHasNoErrors();
            $this->assertSame($active, $area->fresh()->is_active);
        }

        $this->assertSame('Nama diperbaiki', $area->fresh()->name);
        $this->assertSame($area->code, $area->fresh()->code);
        $this->assertSame($area->id, $report->fresh()->area_id);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_code_cannot_be_changed_by_a_tampered_update_request(): void
    {
        $area = Area::factory()->create();
        $this->actingAs(User::factory()->koordinator()->create())->putJson(route('koordinator.areas.update', $area), [
            'code' => 'BARU', 'name' => 'Berubah', 'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertEquals($area->toArray(), $area->fresh()->toArray());
    }

    public function test_duplicate_code_is_rejected_after_case_and_whitespace_normalization(): void
    {
        Area::factory()->create(['code' => 'WIL-01']);
        $this->actingAs(User::factory()->koordinator()->create())->postJson(route('koordinator.areas.store'), [
            'code' => ' wil-01 ', 'name' => 'Wilayah kedua', 'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('areas', 1);
    }

    public function test_maximum_lengths_and_inactive_creation_are_accepted(): void
    {
        $this->actingAs(User::factory()->koordinator()->create())->post(route('koordinator.areas.store'), [
            'code' => str_repeat('A', 20), 'name' => str_repeat('B', 150), 'is_active' => '0',
        ])->assertRedirect(route('koordinator.areas.index'))->assertSessionHasNoErrors();

        $area = Area::sole();
        $this->assertFalse($area->is_active);
        $this->assertSame(str_repeat('A', 20), $area->code);
        $this->assertSame(str_repeat('B', 150), $area->name);
        $this->patch(route('koordinator.areas.update', $area), ['name' => 'Nama baru', 'is_active' => '1'])
            ->assertRedirect(route('koordinator.areas.index'))->assertSessionHasNoErrors();
        $this->assertTrue($area->fresh()->is_active);
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_does_not_create_or_update_data(string $field, mixed $value): void
    {
        $area = Area::factory()->create();
        $this->actingAs(User::factory()->koordinator()->create());
        $input = ['code' => 'VALID', 'name' => 'Nama valid', 'is_active' => true];
        $input[$field] = $value;
        $this->postJson(route('koordinator.areas.store'), $input)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('areas', 1);

        if ($field !== 'code') {
            unset($input['code']);
            $this->putJson(route('koordinator.areas.update', $area), $input)->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertEquals($area->toArray(), $area->fresh()->toArray());
        }
    }

    public function test_validation_redirect_retains_input_and_displays_indonesian_errors(): void
    {
        $this->actingAs(User::factory()->koordinator()->create())->from(route('koordinator.areas.create'))
            ->post(route('koordinator.areas.store'), ['code' => 'WIL-01', 'name' => '', 'is_active' => '1'])
            ->assertRedirect(route('koordinator.areas.create'))->assertSessionHasErrors('name')->assertSessionHasInput('code', 'WIL-01');
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('koordinator.areas.create'))->assertOk()->assertSee('Nama wilayah wajib diisi.')->assertSee('WIL-01');
    }

    public function test_unused_area_can_be_deleted(): void
    {
        $area = Area::factory()->create();
        $this->actingAs(User::factory()->koordinator()->create())->delete(route('koordinator.areas.destroy', $area))
            ->assertRedirect(route('koordinator.areas.index'))->assertSessionHas('status');
        $this->assertModelMissing($area);
    }

    public function test_referenced_area_cannot_be_deleted_even_when_inactive(): void
    {
        $report = Report::factory()->create();
        $area = $report->area;
        $area->update(['is_active' => false]);
        $this->actingAs(User::factory()->koordinator()->create());
        $this->from(route('koordinator.areas.index'))->delete(route('koordinator.areas.destroy', $area))
            ->assertRedirect(route('koordinator.areas.index'))->assertSessionHasErrors('area');
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('koordinator.areas.index'))->assertOk()->assertSee('Wilayah sudah dipakai laporan')->assertSee('Nonaktif')->assertSee('1 laporan');
        $this->deleteJson(route('koordinator.areas.destroy', $area))->assertUnprocessable()->assertJsonValidationErrors('area');
        $this->assertModelExists($area);
        $this->assertModelExists($report);
        $this->assertSame($area->id, $report->fresh()->area_id);
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_all_area_endpoints_reject_unauthorized_users_without_changing_data(?UserRole $role, bool $active): void
    {
        $area = Area::factory()->create();
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }

        $status = $role === null || ! $active ? 401 : 403;
        $input = ['code' => 'SISIPAN', 'name' => 'Permintaan terlarang', 'is_active' => false, 'role' => 'koordinator'];
        foreach (['index', 'create', 'edit'] as $action) {
            $this->getJson(route('koordinator.areas.'.$action, $action === 'edit' ? $area : []))->assertStatus($status);
        }
        $this->postJson(route('koordinator.areas.store'), $input)->assertStatus($status);
        $this->putJson(route('koordinator.areas.update', $area), $input)->assertStatus($status);
        $this->patchJson(route('koordinator.areas.update', $area), $input)->assertStatus($status);
        $this->deleteJson(route('koordinator.areas.destroy', $area))->assertStatus($status);
        $this->assertDatabaseCount('areas', 1);
        $this->assertEquals($area->toArray(), $area->fresh()->toArray());
    }

    public function test_unknown_area_returns_not_found_without_mutating_existing_area(): void
    {
        $area = Area::factory()->create();
        $this->actingAs(User::factory()->koordinator()->create());
        $this->get(route('koordinator.areas.edit', 999999))->assertNotFound();
        $this->putJson(route('koordinator.areas.update', 999999), ['name' => 'Hilang', 'is_active' => true])->assertNotFound();
        $this->deleteJson(route('koordinator.areas.destroy', 999999))->assertNotFound();
        $this->assertEquals($area->toArray(), $area->fresh()->toArray());
    }

    public function test_list_shows_inactive_areas_and_escapes_user_input(): void
    {
        $area = Area::factory()->create(['name' => '<script>alert(1)</script>', 'is_active' => false]);
        $this->actingAs(User::factory()->koordinator()->create())->get(route('koordinator.areas.index'))
            ->assertOk()->assertSee('Nonaktif')->assertSee($area->name)->assertDontSee($area->name, false);
    }

    public static function unauthorizedUsers(): array
    {
        return [
            'guest' => [null, true],
            'pelapor' => [UserRole::Pelapor, true],
            'relawan' => [UserRole::Relawan, true],
            'koordinator nonaktif' => [UserRole::Koordinator, false],
        ];
    }

    public static function invalidInput(): array
    {
        return [
            'kode kosong' => ['code', ' '],
            'kode array' => ['code', ['A']],
            'kode panjang' => ['code', str_repeat('A', 21)],
            'nama kosong' => ['name', ' '],
            'nama array' => ['name', ['A']],
            'nama panjang' => ['name', str_repeat('A', 151)],
            'status kosong' => ['is_active', null],
            'status invalid' => ['is_active', 'aktif'],
        ];
    }
}
