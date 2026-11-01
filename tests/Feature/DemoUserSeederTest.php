<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_an_active_hashed_demo_account_for_each_role(): void
    {
        $this->seed(DemoUserSeeder::class);

        foreach ([
            'pelapor@example.test' => UserRole::Pelapor,
            'pelapor2@example.test' => UserRole::Pelapor,
            'relawan@example.test' => UserRole::Relawan,
            'koordinator@example.test' => UserRole::Koordinator,
        ] as $email => $role) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertSame($role, $user->role);
            $this->assertTrue($user->is_active);
            $this->assertTrue(Hash::check('KarhutlaDemo123!', $user->password));
            $this->assertNotSame('KarhutlaDemo123!', $user->password);
        }

        $this->assertDatabaseCount('users', 4);
    }

    public function test_reseeding_does_not_duplicate_accounts_or_reset_existing_account_changes(): void
    {
        $this->seed(DemoUserSeeder::class);
        $user = User::where('email', 'relawan@example.test')->firstOrFail();
        $user->name = 'Nama relawan yang diperbarui';
        $user->password = 'KataSandiUjiBerbeda123!';
        $user->is_active = false;
        $user->save();
        $passwordHash = $user->password;

        $this->seed(DemoUserSeeder::class);
        $user->refresh();

        $this->assertDatabaseCount('users', 4);
        $this->assertSame('Nama relawan yang diperbarui', $user->name);
        $this->assertSame($passwordHash, $user->password);
        $this->assertFalse($user->is_active);
        $this->assertSame(UserRole::Relawan, $user->role);
    }
}
