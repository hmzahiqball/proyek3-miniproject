<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ResubmitReportRequest extends FormRequest
{
    public function report(): Report
    {
        return $this->user()->reports()->findOrFail($this->route('report'));
    }

    public function authorize(): bool
    {
        Gate::authorize('resubmit', $this->report());

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [];
    }
}
