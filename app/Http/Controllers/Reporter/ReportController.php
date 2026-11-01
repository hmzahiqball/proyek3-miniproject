<?php

namespace App\Http\Controllers\Reporter;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Area;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Report::class);

        return view('reporter.reports.index', [
            'reports' => $request->user()->reports()->with('area')
                ->latest('created_at')->latest('id')->paginate(10),
        ]);
    }

    public function show(Request $request, string $report): View
    {
        $report = $request->user()->reports()->with('area')->findOrFail($report);
        Gate::authorize('view', $report);

        return view('reporter.reports.show', compact('report'));
    }

    public function create(): View
    {
        return view('reporter.reports.form', [
            'report' => new Report,
            'areas' => Area::query()->where('is_active', true)->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function edit(Request $request, string $report): View
    {
        $report = $request->user()->reports()->findOrFail($report);
        Gate::authorize('update', $report);

        return view('reporter.reports.form', [
            'report' => $report,
            'areas' => Area::query()->where('is_active', true)->orWhere('id', $report->area_id)
                ->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function update(UpdateReportRequest $request, string $report): RedirectResponse
    {
        DB::transaction(function () use ($request, $report): void {
            $report = $request->user()->reports()->lockForUpdate()->findOrFail($report);
            Gate::authorize('update', $report);
            $area = Area::query()->lockForUpdate()->find($request->validated('area_id'));

            if (! $area || (! $area->is_active && $area->id !== $report->area_id)) {
                throw ValidationException::withMessages([
                    'area_id' => 'Wilayah pengganti harus aktif. Wilayah lama yang nonaktif boleh dipertahankan.',
                ]);
            }

            $report->fill($request->validated());
            $report->occurred_at = $request->occurredAtUtc();
            $report->save();
        });

        return to_route('pelapor.reports.show', $report)->with('status', 'Perbaikan laporan berhasil disimpan. Status laporan tetap.');
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
