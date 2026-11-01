<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportForwarding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReportForwarding> */
class ReportForwardingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id' => Report::factory()->state(['status' => ReportStatus::Diteruskan]),
            'coordinator_id' => User::factory()->koordinator(),
            'recipient' => 'Pihak Terkait Demo',
            'forwarded_at' => now(),
            'note' => 'Data simulasi: informasi telah diteruskan kepada pihak terkait demo.',
        ];
    }
}
