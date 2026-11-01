<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function __invoke(Report $report): StreamedResponse
    {
        Gate::authorize('downloadEvidence', $report);
        $extension = match ($report->evidence_mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => null,
        };
        abort_unless($extension && $report->evidence_path && Storage::disk('local')->exists($report->evidence_path), 404);

        return Storage::disk('local')->download($report->evidence_path, 'bukti-laporan-'.$report->id.'.'.$extension, [
            'Content-Type' => $report->evidence_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
