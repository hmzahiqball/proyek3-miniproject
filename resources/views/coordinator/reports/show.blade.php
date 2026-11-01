<x-layouts.public title="Detail koordinasi laporan">
    <div class="container-page py-8 sm:py-10">
        <a href="{{ route('koordinator.reports.index') }}" class="text-sm text-forest-900 hover:underline">Kembali ke koordinasi laporan</a>
        <div class="page-heading mt-5">
            <p class="text-sm text-slate-500">Laporan #{{ $report->id }}</p>
            <h1 class="section-title mt-2 break-words">{{ $report->title }}</h1>
            <p class="muted mt-2 break-words">Dikirim oleh {{ $report->reporter->name }}.</p>
            <p class="mt-4 inline-block rounded bg-forest-100 px-3 py-2 text-sm font-medium text-forest-900">{{ $report->status->label() }}</p>
        </div>
        @if($errors->any())
            <div role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Laporan belum ditutup. Periksa catatan penyelesaian.</p>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @can('closeForwarded', $report)
            <form method="POST" action="{{ route('koordinator.reports.close', $report) }}" class="panel mb-6 space-y-4">
                @csrf
                <h2 class="text-lg font-semibold">Tutup proses laporan</h2>
                <div>
                    <label for="closure_note" class="block text-sm font-medium">Catatan penyelesaian</label>
                    <textarea id="closure_note" name="closure_note" rows="4" required minlength="10" maxlength="2000" class="field mt-2 h-auto py-3" aria-describedby="closure-help" @error('closure_note') aria-invalid="true" @enderror>{{ is_string(old('closure_note')) ? old('closure_note') : '' }}</textarea>
                    <p id="closure-help" class="mt-2 text-xs leading-6 text-slate-500">10–2.000 karakter. Jelaskan penyelesaian proses setelah informasi diteruskan.</p>
                </div>
                <p class="muted">Penutupan bersifat final. Status selesai bukan pernyataan bahwa api telah padam.</p>
                <button type="submit" class="btn btn-primary">Tutup laporan</button>
            </form>
        @endcan
        <x-report-details :report="$report" />
        <x-review-history :reviews="$reviews" />
        <section class="panel mt-6" aria-labelledby="forwarding-title">
            <h2 id="forwarding-title" class="text-lg font-semibold">Catatan penerusan</h2>
            @if($report->forwarding)
                <p class="muted mt-3 break-words">Kepada {{ $report->forwarding->recipient }} · {{ $report->forwarding->forwarded_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                <p class="mt-2 text-xs text-slate-500">Dicatat oleh {{ $report->forwarding->coordinator->name }}.</p>
                <p class="mt-3 whitespace-pre-line break-words text-sm leading-7">{{ $report->forwarding->note }}</p>
            @else
                <p class="muted mt-3">Belum ada catatan penerusan.</p>
            @endif
        </section>
        <x-related-reports :report="$report" :reports="$relatedReports" detail-route="koordinator.reports.show" />
    </div>
</x-layouts.public>
