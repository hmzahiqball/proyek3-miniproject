<?php

namespace App\Http\Requests;

use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreForwardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('forward', $this->route('report'));

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string', 'max:150'],
            'forwarded_at' => ['required', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('forwarded_at') && ($error = $this->forwardingTimeError($this->route('report')))) {
                $validator->errors()->add('forwarded_at', $error);
            }
        }];
    }

    public function forwardedAtUtc(): Carbon
    {
        $format = strlen($this->input('forwarded_at')) === 19 ? '!Y-m-d\TH:i:s' : '!Y-m-d\TH:i';

        return Carbon::createFromFormat($format, $this->input('forwarded_at'), 'Asia/Jakarta')->utc();
    }

    public function forwardingTimeError(Report $report): ?string
    {
        $time = $this->forwardedAtUtc();
        if ($time->lessThan($report->created_at)) {
            return 'Waktu penerusan tidak boleh sebelum laporan dibuat.';
        }
        if ($time->isFuture()) {
            return 'Waktu penerusan tidak boleh melebihi waktu saat ini (WIB).';
        }

        return null;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recipient.required' => 'Tujuan penerusan wajib diisi.',
            'recipient.string' => 'Tujuan penerusan harus berupa teks.',
            'recipient.max' => 'Tujuan penerusan maksimal 150 karakter.',
            'forwarded_at.required' => 'Waktu penerusan wajib diisi.',
            'forwarded_at.date_format' => 'Masukkan tanggal dan jam penerusan yang valid dalam WIB.',
            'note.required' => 'Catatan penerusan wajib diisi.',
            'note.string' => 'Catatan penerusan harus berupa teks.',
            'note.min' => 'Catatan penerusan minimal 10 karakter.',
            'note.max' => 'Catatan penerusan maksimal 2.000 karakter.',
        ];
    }
}
