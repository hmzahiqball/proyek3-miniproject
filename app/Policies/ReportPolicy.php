<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReportPolicy
{
    public function monitorAny(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Relawan;
    }

    public function monitor(User $user, Report $report): bool
    {
        return $this->monitorAny($user);
    }

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

    public function startReview(User $user, Report $report): Response
    {
        if (! $this->monitorAny($user)) {
            return Response::deny();
        }

        return $report->status === ReportStatus::Baru
            ? Response::allow()
            : Response::denyWithStatus(409, 'Peninjauan hanya dapat dimulai dari status Baru. Muat ulang laporan untuk melihat status terkini.');
    }

    public function review(User $user, Report $report): Response
    {
        if (! $this->monitorAny($user)) {
            return Response::deny();
        }

        return $report->status === ReportStatus::Ditinjau
            ? Response::allow()
            : Response::denyWithStatus(409, 'Keputusan peninjauan hanya dapat disimpan pada status Ditinjau.');
    }

    public function resubmit(User $user, Report $report): Response
    {
        if (! $this->view($user, $report)->allowed()) {
            return Response::denyAsNotFound();
        }

        return $report->status === ReportStatus::PerluPerbaikan
            ? Response::allow()
            : Response::denyWithStatus(409, 'Kirim ulang hanya tersedia pada status Perlu Perbaikan.');
    }

    public function coordinateAny(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Koordinator;
    }

    public function coordinate(User $user, Report $report): bool
    {
        return $this->coordinateAny($user);
    }

    public function closeForwarded(User $user, Report $report): Response
    {
        if (! $this->coordinateAny($user)) {
            return Response::deny();
        }
        if ($report->status !== ReportStatus::Diteruskan) {
            return Response::denyWithStatus(409, 'Penutupan Koordinator hanya tersedia pada status Diteruskan.');
        }

        return $report->forwarding()->exists()
            ? Response::allow()
            : Response::denyWithStatus(409, 'Catatan penerusan harus tersedia sebelum laporan dapat ditutup.');
    }
}
