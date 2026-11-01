<?php

namespace App\Http\Controllers\Coordinator;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreForwardingRequest;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ForwardingController extends Controller
{
    public function __invoke(StoreForwardingRequest $request, Report $report): RedirectResponse
    {
        DB::transaction(function () use ($request, $report): void {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);
            Gate::authorize('forward', $report);
            if ($error = $request->forwardingTimeError($report)) {
                throw ValidationException::withMessages(['forwarded_at' => $error]);
            }

            $forwarding = $report->forwarding()->make($request->safe()->only(['recipient', 'note']));
            $forwarding->forwarded_at = $request->forwardedAtUtc();
            $forwarding->coordinator()->associate($request->user());
            $forwarding->save();

            $report->status = ReportStatus::Diteruskan;
            $report->save();
        });

        return to_route('koordinator.reports.show', $report)->with('status', 'Penerusan informasi berhasil dicatat.');
    }
}
