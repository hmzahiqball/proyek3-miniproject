<x-layouts.public title="Detail laporan">
    <div class="container-page py-8 sm:py-10">
        <a href="{{ route('pelapor.reports.index') }}" class="text-sm text-forest-900 hover:underline">Kembali ke laporan saya</a>
        <div class="page-heading mt-5">
            <p class="text-sm text-slate-500">Laporan #{{ $report->id }}</p>
            <h1 class="section-title mt-2 break-words">{{ $report->title }}</h1>
            <p class="mt-4 inline-block rounded bg-forest-100 px-3 py-2 text-sm font-medium text-forest-900">{{ $report->status->label() }}</p>
            @can('update', $report)
                <div class="mt-4">
                    <a href="{{ route('pelapor.reports.edit', $report) }}" class="btn btn-outline">Perbaiki laporan</a>
                </div>
            @else
                <p class="muted mt-4">Laporan pada status ini tidak dapat diperbaiki.</p>
            @endcan
        </div>

        <x-report-details :report="$report" />
        <x-review-history :reviews="$reviews" />
        <p class="muted mt-6">Status menunjukkan proses dalam aplikasi. Status selesai bukan pernyataan bahwa api telah padam.</p>
    </div>
</x-layouts.public>
