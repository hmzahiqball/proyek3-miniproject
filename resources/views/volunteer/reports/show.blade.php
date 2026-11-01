<x-layouts.public title="Detail pemantauan laporan">
    <div class="container-page py-8 sm:py-10">
        <a href="{{ route('relawan.reports.index') }}" class="text-sm text-forest-900 hover:underline">Kembali ke pemantauan laporan</a>
        <div class="page-heading mt-5">
            <p class="text-sm text-slate-500">Laporan #{{ $report->id }}</p>
            <h1 class="section-title mt-2 break-words">{{ $report->title }}</h1>
            <p class="muted mt-2 break-words">Dikirim oleh {{ $report->reporter->name }}.</p>
            <p class="mt-4 inline-block rounded bg-forest-100 px-3 py-2 text-sm font-medium text-forest-900">{{ $report->status->label() }}</p>
        </div>

        <x-report-details :report="$report" />
        <x-review-history :reviews="$reviews" />

        <section class="panel mt-6 min-w-0" aria-labelledby="related-title">
            <h2 id="related-title" class="text-lg font-semibold">Laporan terkait di wilayah yang sama</h2>
            <p class="muted mt-2 break-words">Laporan lain di {{ $report->area->name }}, dari seluruh Pelapor.</p>
            <div class="mt-4 space-y-4">
                @forelse($relatedReports as $related)
                    <article class="border-t border-slate-200 pt-4">
                        <h3 class="break-words text-sm font-semibold"><a href="{{ route('relawan.reports.show', $related) }}" class="text-forest-900 hover:underline">{{ $related->title }}</a></h3>
                        <p class="mt-2 text-xs leading-6 text-slate-500">#{{ $related->id }} · {{ $related->status->label() }} · Pengamatan {{ $related->occurred_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                    </article>
                @empty
                    <p class="muted">Belum ada laporan lain di wilayah ini.</p>
                @endforelse
            </div>
            @if($relatedReports->hasPages())
                <div class="mt-5">{{ $relatedReports->links() }}</div>
            @endif
        </section>
        <p class="muted mt-6">Peninjauan komunitas bukan verifikasi resmi kejadian. Status selesai menunjukkan proses dalam aplikasi telah selesai.</p>
    </div>
</x-layouts.public>
