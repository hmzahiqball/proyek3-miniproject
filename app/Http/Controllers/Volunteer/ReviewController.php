<?php

namespace App\Http\Controllers\Volunteer;

use App\Enums\ReportStatus;
use App\Enums\ReviewDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartReviewRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function start(StartReviewRequest $request, Report $report): RedirectResponse
    {
        DB::transaction(function () use ($report): void {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);
            Gate::authorize('startReview', $report);
            $report->status = ReportStatus::Ditinjau;
            $report->closure_note = null;
            $report->closed_at = null;
            $report->save();
        });

        return to_route('relawan.reports.show', $report)->with('status', 'Peninjauan laporan dimulai.');
    }

    public function store(StoreReviewRequest $request, Report $report): RedirectResponse
    {
        DB::transaction(function () use ($request, $report): void {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);
            Gate::authorize('review', $report);
            $decision = ReviewDecision::from($request->validated('decision'));
            abort_if($decision === ReviewDecision::Selesai && $report->forwarding()->exists(), 409, 'Laporan yang sudah diteruskan hanya dapat ditutup oleh Koordinator.');

            $review = $report->reviews()->make(['decision' => $decision, 'note' => $request->validated('note')]);
            $review->reviewer()->associate($request->user());
            $review->save();
            $report->status = match ($decision) {
                ReviewDecision::PerluPerbaikan => ReportStatus::PerluPerbaikan,
                ReviewDecision::PerluDiteruskan => ReportStatus::PerluDiteruskan,
                ReviewDecision::Selesai => ReportStatus::Selesai,
            };
            $report->closure_note = $decision === ReviewDecision::Selesai ? $request->validated('note') : null;
            $report->closed_at = $decision === ReviewDecision::Selesai ? now() : null;
            $report->save();
        });

        return to_route('relawan.reports.show', $report)->with('status', 'Keputusan dan catatan peninjauan berhasil disimpan.');
    }
}
