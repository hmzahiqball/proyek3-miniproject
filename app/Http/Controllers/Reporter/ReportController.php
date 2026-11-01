<?php

namespace App\Http\Controllers\Reporter;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Models\Area;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function create(): View
    {
        return view('reporter.reports.create', [
            'areas' => Area::query()->where('is_active', true)->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $area = Area::query()->lockForUpdate()->find($request->validated('area_id'));

            if (! $area?->is_active) {
                throw ValidationException::withMessages([
                    'area_id' => 'Wilayah tidak tersedia atau sudah nonaktif. Pilih wilayah aktif.',
                ]);
            }

            $report = new Report($request->validated());
            $report->occurred_at = $request->occurredAtUtc();
            $report->reporter()->associate($request->user());
            $report->status = ReportStatus::Baru;
            $report->save();
        });

        return to_route('pelapor.dashboard')->with('status', 'Laporan berhasil dikirim dengan status Baru.');
    }
}
