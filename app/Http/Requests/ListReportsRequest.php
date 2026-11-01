<?php

namespace App\Http\Requests;

use App\Enums\ReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_filter($this->safe()->only(['q', 'area_id', 'status']), fn (mixed $value): bool => $value !== null && $value !== '');
    }

    protected function getRedirectUrl(): string
    {
        return $this->url();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.string' => 'Kata pencarian harus berupa teks.',
            'q.max' => 'Kata pencarian maksimal 150 karakter.',
            'area_id.integer' => 'Pilihan wilayah tidak valid.',
            'area_id.exists' => 'Wilayah yang dipilih tidak tersedia.',
            'status.enum' => 'Status laporan tidak valid.',
            'page.integer' => 'Nomor halaman harus berupa angka bulat.',
            'page.min' => 'Nomor halaman minimal 1.',
            'page.max' => 'Nomor halaman terlalu besar.',
        ];
    }
}
