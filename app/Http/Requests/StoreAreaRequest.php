<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Koordinator;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:areas,code'],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode wilayah wajib diisi.',
            'code.string' => 'Kode wilayah harus berupa teks.',
            'code.max' => 'Kode wilayah maksimal 20 karakter.',
            'code.unique' => 'Kode wilayah sudah digunakan.',
            'name.required' => 'Nama wilayah wajib diisi.',
            'name.string' => 'Nama wilayah harus berupa teks.',
            'name.max' => 'Nama wilayah maksimal 150 karakter.',
            'is_active.required' => 'Pilih status wilayah.',
            'is_active.boolean' => 'Status wilayah tidak valid.',
        ];
    }
}
