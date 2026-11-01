<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StartReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('startReview', $this->route('report'));

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [];
    }
}
