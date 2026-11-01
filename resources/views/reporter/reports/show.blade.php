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

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="panel min-w-0" aria-labelledby="event-title">
                <h2 id="event-title" class="text-lg font-semibold">Informasi kejadian</h2>
                <dl class="mt-5 space-y-5 text-sm">
                    <div>
                        <dt class="text-slate-500">Kondisi yang diamati</dt>
                        <dd class="mt-2 whitespace-pre-line break-words leading-7">{{ $report->description }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Waktu pengamatan</dt>
                        <dd class="mt-2">{{ $report->occurred_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Laporan dikirim</dt>
                        <dd class="mt-2">{{ $report->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd>
                    </div>
                </dl>
            </section>
            <section class="panel min-w-0" aria-labelledby="location-title">
                <h2 id="location-title" class="text-lg font-semibold">Lokasi kejadian</h2>
                <dl class="mt-5 space-y-5 text-sm">
                    <div>
                        <dt class="text-slate-500">Wilayah</dt>
                        <dd class="mt-2 break-words">{{ $report->area->name }} ({{ $report->area->code }})</dd>
                        @if(! $report->area->is_active)
                            <dd class="mt-2 text-xs leading-6 text-slate-500">Wilayah saat ini nonaktif. Laporan tetap tersimpan.</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-slate-500">Petunjuk lokasi</dt>
                        <dd class="mt-2 whitespace-pre-line break-words leading-7">{{ $report->location_description }}</dd>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><dt class="text-slate-500">Lintang</dt><dd class="mt-2 break-all">{{ $report->latitude }}</dd></div>
                        <div><dt class="text-slate-500">Bujur</dt><dd class="mt-2 break-all">{{ $report->longitude }}</dd></div>
                    </div>
                </dl>
            </section>
        </div>
        <p class="muted mt-6">Status menunjukkan proses dalam aplikasi. Status selesai bukan pernyataan bahwa api telah padam.</p>
    </div>
</x-layouts.public>
