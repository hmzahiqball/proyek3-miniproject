<x-layouts.public title="Pemantauan laporan">
    <div class="container-page py-8 sm:py-10">
        <a href="{{ route('relawan.dashboard') }}" class="text-sm text-forest-900 hover:underline">Kembali ke dashboard</a>
        <div class="page-heading mt-5">
            <h1 class="section-title">Pemantauan laporan</h1>
            <p class="muted mt-2">Laporan dari seluruh Pelapor, diurutkan dari yang terbaru.</p>
        </div>

        <div class="space-y-3">
            @forelse($reports as $report)
                <article class="panel min-w-0" aria-labelledby="report-{{ $report->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                        <span>Laporan #{{ $report->id }}</span>
                        <span class="rounded bg-forest-100 px-2.5 py-1.5 font-medium text-forest-900">{{ $report->status->label() }}</span>
                    </div>
                    <h2 id="report-{{ $report->id }}" class="mt-3 break-words text-lg font-semibold">
                        <a href="{{ route('relawan.reports.show', $report) }}" class="text-forest-900 hover:underline">{{ $report->title }}</a>
                    </h2>
                    <p class="muted mt-2 break-words">{{ $report->area->name }} @if(! $report->area->is_active)<span class="text-xs">(wilayah nonaktif)</span>@endif</p>
                    <p class="mt-1 text-xs leading-6 text-slate-500">Dikirim {{ $report->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                    <a href="{{ route('relawan.reports.show', $report) }}" class="btn btn-outline mt-4" aria-label="Lihat laporan {{ $report->title }}">Lihat laporan</a>
                </article>
            @empty
                <p class="panel muted">Belum ada laporan yang masuk.</p>
            @endforelse
        </div>
        @if($reports->hasPages())
            <div class="mt-6">{{ $reports->links() }}</div>
        @endif
    </div>
</x-layouts.public>
