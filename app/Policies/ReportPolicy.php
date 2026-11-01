<?php

namespace App\Policies;

use App\Enums\ReportStatus;
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

    public function update(User $user, Report $report): Response
    {
        if (! $this->view($user, $report)->allowed()) {
            return Response::denyAsNotFound();
        }

        return in_array($report->status, [ReportStatus::Baru, ReportStatus::PerluPerbaikan], true)
            ? Response::allow()
            : Response::denyWithStatus(409, 'Laporan hanya dapat diperbaiki pada status Baru atau Perlu Perbaikan. Muat ulang detail laporan untuk melihat status terkini.');
    }

    public function downloadEvidence(User $user, Report $report): Response
    {
        return $user->is_active && (
            in_array($user->role, [UserRole::Relawan, UserRole::Koordinator], true)
            || $this->view($user, $report)->allowed()
        ) ? Response::allow() : Response::denyAsNotFound();
    }
}
