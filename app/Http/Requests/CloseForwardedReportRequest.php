<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CloseForwardedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('closeForwarded', $this->route('report'));

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['closure_note' => ['required', 'string', 'min:10', 'max:2000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'closure_note.required' => 'Catatan penyelesaian wajib diisi.',
            'closure_note.string' => 'Catatan penyelesaian harus berupa teks.',
            'closure_note.min' => 'Catatan penyelesaian minimal 10 karakter.',
            'closure_note.max' => 'Catatan penyelesaian maksimal 2.000 karakter.',
        ];
    }
}
