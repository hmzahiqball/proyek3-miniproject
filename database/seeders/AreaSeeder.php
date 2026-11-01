<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['A', 'B', 'C'] as $suffix) {
            Area::firstOrCreate(
                ['code' => 'DEMO-'.$suffix],
                ['name' => 'Wilayah Demo '.$suffix, 'is_active' => true],
            );
        }
    }
}
