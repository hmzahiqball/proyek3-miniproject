<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListReportsRequest;
use App\Models\Area;
use App\Models\Report;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(ListReportsRequest $request): View
    {
        Gate::authorize('monitorAny', Report::class);

        return view('volunteer.reports.index', [
            'reports' => Report::query()->with('area')->matchingFilters($request->filters())
                ->latest('created_at')->latest('id')->paginate(10)->appends($request->filters()),
            'filters' => $request->filters(),
            'areas' => Area::query()->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function show(Report $report): View
    {
        Gate::authorize('monitor', $report);
        $report->load(['area', 'reporter:id,name']);

        return view('volunteer.reports.show', [
            'report' => $report,
            'statusLogs' => $report->statusLogs()->with('changedBy:id,name')->latest('created_at')->latest('id')->paginate(10, ['*'], 'riwayat'),
            'reviews' => $report->reviews()->with('reviewer:id,name')->latest('created_at')->latest('id')->paginate(10, ['*'], 'catatan'),
            'relatedReports' => Report::query()->where('area_id', $report->area_id)->whereKeyNot($report->id)
                ->latest('created_at')->latest('id')->paginate(5, ['*'], 'terkait'),
        ]);
    }
}
