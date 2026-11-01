<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        Gate::authorize('monitorAny', Report::class);

        return view('volunteer.reports.index', [
            'reports' => Report::query()->with('area')->latest('created_at')->latest('id')->paginate(10),
        ]);
    }

    public function show(Report $report): View
    {
        Gate::authorize('monitor', $report);
        $report->load(['area', 'reporter:id,name']);

        return view('volunteer.reports.show', [
            'report' => $report,
            'reviews' => $report->reviews()->with('reviewer:id,name')->latest('created_at')->latest('id')->paginate(10, ['*'], 'catatan'),
            'relatedReports' => Report::query()->where('area_id', $report->area_id)->whereKeyNot($report->id)
                ->latest('created_at')->latest('id')->paginate(5, ['*'], 'terkait'),
        ]);
    }
}
