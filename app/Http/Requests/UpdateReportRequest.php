<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateReportRequest extends StoreReportRequest
{
    private ?Report $ownedReport = null;

    public function report(): Report
    {
        return $this->ownedReport ??= $this->user()->reports()->findOrFail($this->route('report'));
    }

    public function authorize(): bool
    {
        Gate::authorize('update', $this->report());

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['area_id'] = [
            'required', 'integer', Rule::exists('areas', 'id')->where(function (Builder $query): void {
                $query->where(function (Builder $areas): void {
                    $areas->where('is_active', true)->orWhere('id', $this->report()->area_id);
                });
            }),
        ];

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'area_id.exists' => 'Wilayah pengganti harus aktif. Wilayah lama yang nonaktif boleh dipertahankan.',
        ];
    }
}
