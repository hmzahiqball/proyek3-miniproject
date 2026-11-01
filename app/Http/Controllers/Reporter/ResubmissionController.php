<?php

namespace App\Http\Controllers\Reporter;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResubmitReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class ResubmissionController extends Controller
{
    public function __invoke(ResubmitReportRequest $request, string $report): RedirectResponse
    {
        DB::transaction(function () use ($request, $report): void {
            $report = $request->user()->reports()->lockForUpdate()->findOrFail($report);
            Gate::authorize('resubmit', $report);
            $this->validateStoredReport($report);
            $report->status = ReportStatus::Ditinjau;
            $report->closure_note = null;
            $report->closed_at = null;
            $report->save();
        });

        return to_route('pelapor.reports.show', $report)->with('status', 'Laporan berhasil dikirim ulang untuk ditinjau.');
    }

    private function validateStoredReport(Report $report): void
    {
        $data = $report->only(['title', 'description', 'area_id', 'location_description', 'latitude', 'longitude']);
        $data['occurred_at'] = $report->occurred_at?->utc()->format('Y-m-d H:i:s');
        $data['evidence'] = null;
        if ($report->evidence_path) {
            $disk = Storage::disk('local');
            if (! $disk->exists($report->evidence_path)) {
                throw ValidationException::withMessages(['evidence' => 'Bukti tidak tersedia. Perbaiki laporan dan unggah kembali bukti sebelum kirim ulang.']);
            }
            try {
                $data['evidence'] = new UploadedFile($disk->path($report->evidence_path), basename($report->evidence_path), null, UPLOAD_ERR_OK, true);
            } catch (FileNotFoundException) {
                throw ValidationException::withMessages(['evidence' => 'Bukti tidak tersedia. Unggah kembali bukti sebelum kirim ulang.']);
            }
            if ($data['evidence']->getMimeType() !== $report->evidence_mime || $data['evidence']->getSize() !== $report->evidence_size) {
                throw ValidationException::withMessages(['evidence' => 'Bukti tersimpan tidak sesuai. Unggah kembali bukti sebelum kirim ulang.']);
            }
        } elseif ($report->evidence_mime !== null || $report->evidence_size !== null) {
            throw ValidationException::withMessages(['evidence' => 'Data bukti tidak lengkap. Perbaiki bukti sebelum kirim ulang.']);
        }

        $rules = StoreReportRequest::reportInputRules();
        $rules['area_id'] = ['required', 'integer', Rule::exists('areas', 'id')];
        $rules['occurred_at'] = ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:'.now()->format('Y-m-d H:i:s')];
        Validator::make($data, $rules, [
            ...StoreReportRequest::reportInputMessages(),
            'occurred_at.date_format' => 'Waktu pengamatan laporan tidak valid.',
            'occurred_at.before_or_equal' => 'Waktu pengamatan tidak boleh melebihi waktu saat ini.',
        ])->validate();
    }
}
