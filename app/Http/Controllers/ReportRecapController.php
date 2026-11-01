<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\ReportRecapRequest;
use App\Models\Area;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ReportRecapController extends Controller
{
    public function __invoke(ReportRecapRequest $request): View
    {
        $areaId = $request->validated('area_id');
        $rows = Area::query()->when($areaId, fn (Builder $query): Builder => $query->whereKey($areaId))
            ->withCount([
                'reports as total_reports',
                'reports as active_reports' => fn (Builder $query): Builder => $query->where('status', '!=', ReportStatus::Selesai),
                'reports as completed_reports' => fn (Builder $query): Builder => $query->where('status', ReportStatus::Selesai),
            ])->orderBy('name')->orderBy('id')->get();

        return view('reports.recap', [
            'areas' => Area::query()->orderBy('name')->orderBy('id')->get(),
            'areaId' => $areaId,
            'rows' => $rows,
            'summary' => [
                'total' => (int) $rows->sum('total_reports'),
                'active' => (int) $rows->sum('active_reports'),
                'completed' => (int) $rows->sum('completed_reports'),
            ],
        ]);
    }
}
