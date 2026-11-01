<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Area;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Report> */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'area_id' => Area::factory(),
            'title' => 'Pengamatan asap di wilayah demo',
            'description' => 'Data simulasi: terlihat asap dari lahan di sebelah timur jalan.',
            'occurred_at' => now()->subHour(),
            'location_description' => 'Lahan demo di sebelah timur jalan utama.',
            'latitude' => '-6.9000000',
            'longitude' => '107.6000000',
            'status' => ReportStatus::Baru,
        ];
    }
}
