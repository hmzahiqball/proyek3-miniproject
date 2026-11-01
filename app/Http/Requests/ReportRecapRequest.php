<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReportRecapRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('recapAny', Report::class);

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['area_id' => ['nullable', 'integer', 'exists:areas,id']];
    }

    protected function getRedirectUrl(): string
    {
        return route('reports.recap');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'area_id.integer' => 'Pilihan wilayah tidak valid.',
            'area_id.exists' => 'Wilayah yang dipilih tidak tersedia.',
        ];
    }
}
