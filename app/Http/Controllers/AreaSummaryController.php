<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Resources\AreaSummaryResource;
use App\Models\Area;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaSummaryController extends Controller
{
    public function __invoke(Request $request, Area $area): JsonResponse
    {
        $area->loadCount(['reports as active_report_count' => function (Builder $query) use ($request): void {
            $query->where('status', '!=', ReportStatus::Selesai);
            if ($request->user()->role === UserRole::Pelapor) {
                $query->where('reporter_id', $request->user()->id);
            }
        }]);

        return (new AreaSummaryResource($area))->response()->header('Cache-Control', 'private, no-store');
    }
}
