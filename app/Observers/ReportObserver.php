<?php

namespace App\Observers;

use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ReportObserver
{
    public function updated(Report $report): void
    {
        if (! $report->wasChanged('status')) {
            return;
        }

        $log = $report->statusLogs()->make([
            'from_status' => $report->getRawOriginal('status'),
            'to_status' => $report->status,
        ]);
        $log->changed_by = Auth::id();
        if (! $log->save()) {
            throw new RuntimeException('Riwayat perubahan status gagal disimpan.');
        }
    }
}
