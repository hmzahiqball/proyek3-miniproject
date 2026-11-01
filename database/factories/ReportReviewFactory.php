<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Enums\ReviewDecision;
use App\Models\Report;
use App\Models\ReportReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReportReview> */
class ReportReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id' => Report::factory()->state(['status' => ReportStatus::PerluPerbaikan]),
            'reviewer_id' => User::factory()->relawan(),
            'decision' => ReviewDecision::PerluPerbaikan,
            'note' => 'Data simulasi: mohon lengkapi petunjuk lokasi pengamatan.',
        ];
    }
}
