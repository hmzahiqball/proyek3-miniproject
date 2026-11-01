<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new RuntimeException('Pengujian database harus memakai database terpisah dengan akhiran _testing.');
        }
    }

    public function test_database_defaults_new_users_to_active_reporters(): void
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Pelapor Uji',
            'email' => 'pelapor@example.test',
            'password' => 'hash-for-schema-test',
        ]);

        $user = User::findOrFail($id);

        $this->assertSame(UserRole::Pelapor, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_user_factory_supports_volunteer_coordinator_and_inactive_accounts(): void
    {
        $volunteer = User::factory()->relawan()->create();
        $coordinator = User::factory()->koordinator()->inactive()->create();

        $this->assertSame(UserRole::Relawan, $volunteer->fresh()->role);
        $this->assertSame(UserRole::Koordinator, $coordinator->fresh()->role);
        $this->assertFalse($coordinator->fresh()->is_active);
    }

    public function test_public_user_fields_cannot_mass_assign_role_or_active_status(): void
    {
        $user = User::factory()->make();
        $user->fill(['role' => UserRole::Koordinator, 'is_active' => false]);

        $this->assertSame(UserRole::Pelapor, $user->role);
        $this->assertTrue($user->is_active);
    }
}
