<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Pelapor;
    }

    public function view(User $user, Report $report): Response
    {
        return $this->viewAny($user) && $user->id === $report->reporter_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
