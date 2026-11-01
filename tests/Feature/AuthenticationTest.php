<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_enabled_login_form(): void
    {
        $this->get(route('access'))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('Login belum tersedia');
    }

    #[DataProvider('userRoles')]
    public function test_each_active_role_can_login_and_view_its_account(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()->assertSee($user->name)->assertSee($role->label());
    }

    public static function userRoles(): array
    {
        return [
            'pelapor' => [UserRole::Pelapor],
            'relawan' => [UserRole::Relawan],
            'koordinator' => [UserRole::Koordinator],
        ];
    }

    public function test_login_normalizes_email_without_changing_the_password(): void
    {
        $user = User::factory()->create(['email' => 'pelapor@example.test', 'password' => 'SandiUji123!']);

        $this->post(route('login.store'), ['email' => '  PELAPOR@EXAMPLE.TEST  ', 'password' => 'SandiUji123!'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('rejectedAccounts')]
    public function test_invalid_credentials_or_inactive_accounts_cannot_login(string $scenario): void
    {
        $user = User::factory()->create(['is_active' => $scenario !== 'inactive']);
        $email = $scenario === 'unknown' ? 'unknown@example.test' : $user->email;
        $password = $scenario === 'wrong-password' ? 'wrong-password' : 'password';

        $this->from(route('access'))->post(route('login.store'), [
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ])->assertRedirect(route('access'))
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak sesuai.'])
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
        $this->assertSame($scenario !== 'inactive', $user->fresh()->is_active);
    }

    public static function rejectedAccounts(): array
    {
        return [
            'wrong password' => ['wrong-password'],
            'unknown account' => ['unknown'],
            'inactive account' => ['inactive'],
        ];
    }

    #[DataProvider('invalidLoginInput')]
    public function test_invalid_login_payload_is_rejected(array $payload, array $fields): void
    {
        $this->postJson(route('login.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($fields);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidLoginInput(): array
    {
        return [
            'empty fields' => [[], ['email', 'password']],
            'malformed email' => [['email' => 'not-an-email', 'password' => 'password'], ['email']],
            'array email' => [['email' => ['user@example.test'], 'password' => 'password'], ['email']],
            'array password' => [['email' => 'user@example.test', 'password' => ['password']], ['password']],
            'oversized password' => [['email' => 'user@example.test', 'password' => str_repeat('x', 256)], ['password']],
        ];
    }

    public function test_role_and_owner_payload_do_not_change_the_authenticated_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->koordinator()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'role' => 'koordinator',
            'user_id' => $other->id,
            'redirect' => 'https://example.test/untrusted',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Pelapor, $user->fresh()->role);
    }

    public function test_guest_cannot_open_the_dashboard_and_can_continue_after_login(): void
    {
        $user = User::factory()->create();

        $this->get(route('dashboard'))->assertRedirect(route('access'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_guest_json_request_to_dashboard_receives_unauthorized(): void
    {
        $this->getJson(route('dashboard'))->assertUnauthorized();
    }

    public function test_authenticated_user_is_redirected_away_from_login_and_cannot_switch_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->koordinator()->create();
        $this->actingAs($user)->get(route('access'))->assertRedirect(route('dashboard'));
        $this->post(route('login.store'), ['email' => $other->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rotates_the_session_id_and_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->withSession(['_token' => 'old-csrf-token']);
        $sessionId = session()->getId();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame('old-csrf-token', session()->token());
    }

    public function test_logout_clears_authentication_invalidates_session_and_rotates_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private_data' => 'test-value', '_token' => 'old-csrf-token']);
        $sessionId = session()->getId();

        $this->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Anda sudah keluar dari akun.')
            ->assertSessionMissing('private_data');

        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame('old-csrf-token', session()->token());
        $this->get(route('dashboard'))->assertRedirect(route('access'));
    }

    public function test_logout_is_not_available_as_a_get_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('logout'))->assertStatus(405);
        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_cannot_call_authenticated_logout(): void
    {
        $this->post(route('logout'))->assertRedirect(route('access'));
        $this->postJson(route('logout'))->assertUnauthorized();
    }

    public function test_account_deactivated_after_login_is_logged_out_on_next_protected_request(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $user->is_active = false;
        $user->save();
        Auth::forgetGuards();

        $this->get(route('dashboard'))->assertRedirect(route('access'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('access'))->assertOk()->assertSee('Akun Anda tidak aktif.');
    }

    public function test_inactive_session_json_request_is_rejected_and_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->getJson(route('dashboard'))
            ->assertUnauthorized()->assertJson(['message' => 'Akun Anda tidak aktif. Silakan hubungi koordinator.']);

        $this->assertGuest();
    }

    public function test_login_throttles_repeated_failures_and_allows_login_after_the_window_expires(): void
    {
        $user = User::factory()->create(['email' => 'pelapor@example.test']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('access'))->post(route('login.store'), ['email' => strtoupper($user->email), 'password' => 'wrong'])
                ->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak sesuai.']);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak percobaan masuk.', session('errors')->first('email'));
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_clears_previous_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($round = 0; $round < 2; $round++) {
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
                    ->assertSessionHasErrors('email');
            }

            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
        }
    }

    public function test_long_emails_are_safely_throttled_with_the_database_cache(): void
    {
        config(['cache.default' => 'database']);
        $domain = implode('.', array_fill(0, 4, str_repeat('b', 48))).'.test';
        $email = str_repeat('a', 48).'@'.$domain;
        $user = User::factory()->create(['email' => $email]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'wrong'])
                ->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak sesuai.']);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak percobaan masuk.', session('errors')->first('email'));
        $this->assertGreaterThan(0, DB::table('cache')->count());
        $this->assertGuest();
    }
}
