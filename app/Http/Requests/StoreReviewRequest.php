<?php

namespace App\Http\Requests;

use App\Enums\ReviewDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('review', $this->route('report'));

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision.required' => 'Pilih keputusan peninjauan.',
            'decision.enum' => 'Keputusan peninjauan tidak valid.',
            'note.required' => 'Catatan alasan wajib diisi.',
            'note.string' => 'Catatan alasan harus berupa teks.',
            'note.min' => 'Catatan alasan minimal 10 karakter.',
            'note.max' => 'Catatan alasan maksimal 2.000 karakter.',
        ];
    }
}
