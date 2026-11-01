<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseForwardedReportRequest;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClosureController extends Controller
{
    public function __invoke(CloseForwardedReportRequest $request, Report $report): RedirectResponse
    {
        DB::transaction(function () use ($request, $report): void {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);
            Gate::authorize('closeForwarded', $report);
            abort_unless($report->forwarding()->lockForUpdate()->first(), 409, 'Catatan penerusan belum tersedia.');
            $report->closure_note = $request->validated('closure_note');
            $report->closed_at = now();
            $report->status = ReportStatus::Selesai;
            $report->save();
        });

        return to_route('koordinator.reports.show', $report)->with('status', 'Proses laporan berhasil ditutup.');
    }
}
