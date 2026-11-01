<?php

namespace App\Http\Controllers\Reporter;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Area;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Report::class);

        return view('reporter.reports.index', [
            'reports' => $request->user()->reports()->with('area')
                ->latest('created_at')->latest('id')->paginate(10),
        ]);
    }

    public function show(Request $request, string $report): View
    {
        $report = $request->user()->reports()->with('area')->findOrFail($report);
        Gate::authorize('view', $report);

        return view('reporter.reports.show', compact('report'));
    }

    public function create(): View
    {
        return view('reporter.reports.form', [
            'report' => new Report,
            'areas' => Area::query()->where('is_active', true)->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function edit(Request $request, string $report): View
    {
        $report = $request->user()->reports()->findOrFail($report);
        Gate::authorize('update', $report);

        return view('reporter.reports.form', [
            'report' => $report,
            'areas' => Area::query()->where('is_active', true)->orWhere('id', $report->area_id)
                ->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function update(UpdateReportRequest $request, string $report): RedirectResponse
    {
        $newPath = null;
        $oldPath = null;
        try {
            DB::transaction(function () use ($request, $report, &$newPath, &$oldPath): void {
                $report = $request->user()->reports()->lockForUpdate()->findOrFail($report);
                Gate::authorize('update', $report);
                $area = Area::query()->lockForUpdate()->find($request->validated('area_id'));

                if (! $area || (! $area->is_active && $area->id !== $report->area_id)) {
                    throw ValidationException::withMessages([
                        'area_id' => 'Wilayah pengganti harus aktif. Wilayah lama yang nonaktif boleh dipertahankan.',
                    ]);
                }

                $report->fill($request->safe()->except('evidence'));
                $report->occurred_at = $request->occurredAtUtc();
                if ($request->hasFile('evidence')) {
                    $oldPath = $report->evidence_path;
                    $this->storeEvidence($report, $request->file('evidence'), $newPath);
                }
                $report->save();
            });
        } catch (Throwable $exception) {
            $this->deleteEvidenceSafely($newPath);
            throw $exception;
        }
        $this->deleteEvidenceSafely($oldPath);

        return to_route('pelapor.reports.show', $report)->with('status', 'Perbaikan laporan berhasil disimpan. Status laporan tetap.');
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $newPath = null;
        try {
            DB::transaction(function () use ($request, &$newPath): void {
                $area = Area::query()->lockForUpdate()->find($request->validated('area_id'));

                if (! $area?->is_active) {
                    throw ValidationException::withMessages([
                        'area_id' => 'Wilayah tidak tersedia atau sudah nonaktif. Pilih wilayah aktif.',
                    ]);
                }

                $report = new Report($request->safe()->except('evidence'));
                $report->occurred_at = $request->occurredAtUtc();
                $report->reporter()->associate($request->user());
                $report->status = ReportStatus::Baru;
                $this->storeEvidence($report, $request->file('evidence'), $newPath);
                $report->save();
            });
        } catch (Throwable $exception) {
            $this->deleteEvidenceSafely($newPath);
            throw $exception;
        }

        return to_route('pelapor.dashboard')->with('status', 'Laporan berhasil dikirim dengan status Baru.');
    }

    private function storeEvidence(Report $report, ?UploadedFile $file, ?string &$newPath): void
    {
        if (! $file) {
            return;
        }

        $newPath = 'report-evidence/'.$file->hashName();
        if (! $file->storeAs('report-evidence', $file->hashName(), 'local')) {
            throw ValidationException::withMessages(['evidence' => 'Bukti gagal disimpan. Silakan pilih file dan coba lagi.']);
        }

        $report->evidence_path = $newPath;
        $report->evidence_mime = $file->getMimeType();
        $report->evidence_size = $file->getSize();
    }

    private function deleteEvidenceSafely(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            if (! Storage::disk('local')->delete($path)) {
                Log::warning('File bukti gagal dibersihkan.', ['path' => $path]);
            }
        } catch (Throwable $exception) {
            Log::warning('File bukti gagal dibersihkan.', ['path' => $path, 'exception' => $exception]);
        }
    }
}
