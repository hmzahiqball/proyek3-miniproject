<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReportStatusLog> */
class ReportStatusLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id' => Report::factory()->state(['status' => ReportStatus::Ditinjau]),
            'changed_by' => User::factory()->relawan(),
            'from_status' => ReportStatus::Baru,
            'to_status' => ReportStatus::Ditinjau,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => ['changed_by' => null]);
    }
}
