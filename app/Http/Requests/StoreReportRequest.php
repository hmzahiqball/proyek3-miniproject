<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Pelapor;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'occurred_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'location_description' => ['required', 'string', 'min:10', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'extensions:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('occurred_at')) {
                return;
            }

            if ($this->occurredAtUtc()->year < 1000) {
                $validator->errors()->add('occurred_at', 'Waktu pengamatan di luar rentang tanggal yang didukung.');
            } elseif ($this->occurredAtUtc()->isFuture()) {
                $validator->errors()->add('occurred_at', 'Waktu pengamatan tidak boleh melebihi waktu saat ini (WIB).');
            }
        }];
    }

    public function occurredAtUtc(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d\TH:i', $this->input('occurred_at'), 'Asia/Jakarta')->utc();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Ringkasan kejadian wajib diisi.',
            'title.string' => 'Ringkasan kejadian harus berupa teks.',
            'title.min' => 'Ringkasan kejadian minimal 5 karakter.',
            'title.max' => 'Ringkasan kejadian maksimal 150 karakter.',
            'description.required' => 'Kondisi yang diamati wajib diisi.',
            'description.string' => 'Kondisi yang diamati harus berupa teks.',
            'description.min' => 'Kondisi yang diamati minimal 20 karakter.',
            'description.max' => 'Kondisi yang diamati maksimal 5.000 karakter.',
            'occurred_at.required' => 'Waktu pengamatan wajib diisi.',
            'occurred_at.date_format' => 'Masukkan tanggal dan jam pengamatan yang valid dalam WIB.',
            'area_id.required' => 'Pilih wilayah kejadian.',
            'area_id.integer' => 'Pilihan wilayah tidak valid.',
            'area_id.exists' => 'Wilayah tidak tersedia atau sudah nonaktif. Pilih wilayah aktif.',
            'location_description.required' => 'Petunjuk lokasi wajib diisi.',
            'location_description.string' => 'Petunjuk lokasi harus berupa teks.',
            'location_description.min' => 'Petunjuk lokasi minimal 10 karakter.',
            'location_description.max' => 'Petunjuk lokasi maksimal 500 karakter.',
            'latitude.required' => 'Lintang wajib diisi.',
            'latitude.numeric' => 'Lintang harus berupa angka dengan titik sebagai pemisah desimal.',
            'latitude.between' => 'Lintang harus berada antara -90 dan 90.',
            'longitude.required' => 'Bujur wajib diisi.',
            'longitude.numeric' => 'Bujur harus berupa angka dengan titik sebagai pemisah desimal.',
            'longitude.between' => 'Bujur harus berada antara -180 dan 180.',
            'evidence.file' => 'Bukti harus berupa satu file.',
            'evidence.uploaded' => 'Bukti gagal diunggah. Pastikan ukurannya maksimal 2 MB.',
            'evidence.mimes' => 'Bukti hanya boleh berupa JPG/JPEG, PNG, atau PDF yang valid.',
            'evidence.mimetypes' => 'Isi bukti harus berupa gambar JPEG/PNG atau dokumen PDF.',
            'evidence.extensions' => 'Ekstensi bukti harus JPG, JPEG, PNG, atau PDF.',
            'evidence.max' => 'Ukuran bukti maksimal 2 MB (2.048 KB).',
        ];
    }
}
