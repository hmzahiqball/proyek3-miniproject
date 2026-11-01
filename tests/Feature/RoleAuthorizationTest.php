<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roleRoutes')]
    public function test_dashboard_dispatcher_uses_the_role_stored_in_the_database(string $route, UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('dashboard', ['role' => 'koordinator']))->assertRedirect(route($route));
    }

    #[DataProvider('accessMatrix')]
    public function test_each_role_can_only_open_its_own_dashboard(UserRole $role, string $route, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);
        $response = $this->actingAs($user)->get(route($route));

        if ($allowed) {
            $response->assertOk()->assertSee($user->name)->assertSee('Dashboard '.$role->label());

            if ($role === UserRole::Koordinator) {
                $response->assertSee(route('koordinator.areas.index'));
                $response->assertSee(route('koordinator.reports.index'));
            } else {
                $response->assertDontSee('Kelola wilayah');
                $response->assertDontSee(route('koordinator.reports.index'));
            }

            if ($role === UserRole::Pelapor) {
                $response->assertSee(route('pelapor.reports.create'));
                $response->assertSee(route('pelapor.reports.index'));
            } else {
                $response->assertDontSee(route('pelapor.reports.create'));
                $response->assertDontSee(route('pelapor.reports.index'));
            }

            if ($role === UserRole::Relawan) {
                $response->assertSee(route('relawan.reports.index'));
            } else {
                $response->assertDontSee(route('relawan.reports.index'));
            }
        } else {
            $response->assertForbidden()->assertSee('Akses ditolak');
            $this->getJson(route($route))->assertForbidden()
                ->assertJson(['message' => 'Anda tidak memiliki akses ke halaman ini.']);
        }

        $this->assertSame($role, $user->fresh()->role);
        $this->assertDatabaseCount('reports', 0);
    }

    #[DataProvider('roleRoutes')]
    public function test_guest_is_rejected_from_each_role_dashboard(string $route, UserRole $role): void
    {
        $this->get(route($route))->assertRedirect(route('access'));
        $this->getJson(route($route))->assertUnauthorized();
    }

    #[DataProvider('roleRoutes')]
    public function test_inactive_accounts_cannot_open_role_dashboards(string $route, UserRole $role): void
    {
        $user = User::factory()->inactive()->create(['role' => $role]);

        $this->actingAs($user)->get(route($route))->assertRedirect(route('access'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->getJson(route($route))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_query_parameters_and_headers_cannot_impersonate_another_role(): void
    {
        $reporter = User::factory()->create();
        $coordinator = User::factory()->koordinator()->create();

        $this->actingAs($reporter)->withHeader('X-Role', 'koordinator')->get(route('koordinator.dashboard', [
            'role' => 'koordinator',
            'user_id' => $coordinator->id,
        ]))->assertForbidden()->assertDontSee($coordinator->email);

        $this->assertSame(UserRole::Pelapor, $reporter->fresh()->role);
    }

    #[DataProvider('userRoles')]
    public function test_role_middleware_blocks_unauthorized_post_before_database_mutation(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $area = Area::factory()->create(['name' => 'Wilayah sebelum perubahan']);

        Route::middleware(['web', 'auth', 'active', 'role:koordinator'])->post('/uji-otorisasi', function () use ($area): Response {
            $area->update(['name' => 'Wilayah setelah perubahan yang sah']);

            return response()->noContent();
        });

        $response = $this->actingAs($user)->postJson('/uji-otorisasi', ['role' => 'koordinator', 'is_active' => true]);

        if ($role === UserRole::Koordinator) {
            $response->assertNoContent();
            $this->assertSame('Wilayah setelah perubahan yang sah', $area->fresh()->name);
        } else {
            $response->assertForbidden();
            $this->assertSame('Wilayah sebelum perubahan', $area->fresh()->name);
        }

        $this->assertSame($role, $user->fresh()->role);
        $this->assertDatabaseCount('areas', 1);
    }

    #[DataProvider('userRoles')]
    public function test_explicit_multiple_role_access_does_not_grant_access_to_other_roles(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        Route::middleware(['web', 'auth', 'active', 'role:relawan,koordinator'])->get('/uji-akses-bersama', function (): Response {
            return response()->noContent();
        });

        $this->actingAs($user)->getJson('/uji-akses-bersama')
            ->assertStatus($role === UserRole::Pelapor ? 403 : 204);
    }

    public function test_intended_login_destination_cannot_bypass_role_authorization(): void
    {
        $reporter = User::factory()->create();
        $this->get(route('koordinator.dashboard'))->assertRedirect(route('access'));

        $this->withCookie(config('session.cookie'), session()->getId())->post(route('login.store'), [
            'email' => $reporter->email,
            'password' => 'password',
        ])->assertRedirect(route('koordinator.dashboard'));

        $this->get(route('koordinator.dashboard'))->assertForbidden();
        $this->get(route('dashboard'))->assertRedirect(route('pelapor.dashboard'));
    }

    public static function roleRoutes(): array
    {
        return [
            'pelapor' => ['pelapor.dashboard', UserRole::Pelapor],
            'relawan' => ['relawan.dashboard', UserRole::Relawan],
            'koordinator' => ['koordinator.dashboard', UserRole::Koordinator],
        ];
    }

    public static function userRoles(): array
    {
        return [
            'pelapor' => [UserRole::Pelapor],
            'relawan' => [UserRole::Relawan],
            'koordinator' => [UserRole::Koordinator],
        ];
    }

    public static function accessMatrix(): array
    {
        $cases = [];

        foreach (UserRole::cases() as $role) {
            foreach (self::roleRoutes() as [$route, $requiredRole]) {
                $cases[$role->value.' to '.$requiredRole->value] = [$role, $route, $role === $requiredRole];
            }
        }

        return $cases;
    }
}
