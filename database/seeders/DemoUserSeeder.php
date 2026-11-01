<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Pelapor Demo', 'pelapor@example.test', UserRole::Pelapor],
            ['Pelapor Kedua Demo', 'pelapor2@example.test', UserRole::Pelapor],
            ['Relawan Demo', 'relawan@example.test', UserRole::Relawan],
            ['Koordinator Demo', 'koordinator@example.test', UserRole::Koordinator],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);

            if ($user->exists) {
                continue;
            }

            $user->name = $name;
            $user->password = 'KarhutlaDemo123!';
            $user->role = $role;
            $user->is_active = true;
            $user->save();
        }
    }
}
