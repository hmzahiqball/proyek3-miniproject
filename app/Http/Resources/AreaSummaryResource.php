<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AreaSummaryResource extends JsonResource
{
    /** @return array{area_id: int, area_name: string, active_report_count: int, scope: string} */
    public function toArray(Request $request): array
    {
        return [
            'area_id' => $this->id,
            'area_name' => $this->name,
            'active_report_count' => (int) $this->active_report_count,
            'scope' => $request->user()->role === UserRole::Pelapor ? 'milik_sendiri' : 'seluruh_laporan',
        ];
    }
}
