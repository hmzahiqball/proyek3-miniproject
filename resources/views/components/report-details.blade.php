@props(['report'])
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
        <section class="panel mt-6" aria-labelledby="evidence-title">
            <h2 id="evidence-title" class="text-lg font-semibold">Bukti pendukung</h2>
            @if($report->evidence_path)
                <p class="muted mt-2">{{ $report->evidence_mime === 'application/pdf' ? 'Dokumen PDF' : 'Gambar' }} · {{ number_format($report->evidence_size / 1024, 1, ',', '.') }} KB</p>
                <a href="{{ route('reports.evidence', $report) }}" class="btn btn-outline mt-4">Unduh bukti</a>
            @else
                <p class="muted mt-2">Tidak ada bukti yang dilampirkan.</p>
            @endif
        </section>

