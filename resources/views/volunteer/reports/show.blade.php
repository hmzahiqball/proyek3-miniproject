<x-layouts.public title="Detail pemantauan laporan">
    <div class="container-page py-8 sm:py-10">
        <a href="{{ route('relawan.reports.index') }}" class="text-sm text-forest-900 hover:underline">Kembali ke pemantauan laporan</a>
        <div class="page-heading mt-5">
            <p class="text-sm text-slate-500">Laporan #{{ $report->id }}</p>
            <h1 class="section-title mt-2 break-words">{{ $report->title }}</h1>
            <p class="muted mt-2 break-words">Dikirim oleh {{ $report->reporter->name }}.</p>
            <p class="mt-4 inline-block rounded bg-forest-100 px-3 py-2 text-sm font-medium text-forest-900">{{ $report->status->label() }}</p>
        </div>

        @if($errors->any())
            <div role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Keputusan belum disimpan. Periksa isian berikut.</p>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @can('startReview', $report)
            <form method="POST" action="{{ route('relawan.reports.start-review', $report) }}" class="panel mb-6">
                @csrf
                <p class="muted mb-4">Mulai peninjauan untuk memeriksa laporan dan mencatat keputusan. Selama ditinjau, isi laporan tidak dapat diubah Pelapor.</p>
                <button type="submit" class="btn btn-primary">Mulai peninjauan</button>
            </form>
        @endcan

        @can('review', $report)
            <form method="POST" action="{{ route('relawan.reports.review', $report) }}" class="panel mb-6 space-y-5">
                @csrf
                <h2 class="text-lg font-semibold">Keputusan peninjauan</h2>
                <div>
                    <label for="decision" class="block text-sm font-medium">Keputusan</label>
                    <select id="decision" name="decision" required class="field mt-2" @error('decision') aria-invalid="true" @enderror>
                        <option value="">Pilih keputusan</option>
                        @foreach(\App\Enums\ReviewDecision::cases() as $decision)
                            <option value="{{ $decision->value }}" @selected(is_string(old('decision')) && old('decision') === $decision->value)>{{ $decision === \App\Enums\ReviewDecision::Selesai ? 'Selesai tanpa penerusan' : $decision->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="note" class="block text-sm font-medium">Catatan alasan</label>
                    <textarea id="note" name="note" required minlength="10" maxlength="2000" rows="4" class="field mt-2 h-auto py-3" aria-describedby="note-help" @error('note') aria-invalid="true" @enderror>{{ is_string(old('note')) ? old('note') : '' }}</textarea>
                    <p id="note-help" class="mt-2 text-xs leading-6 text-slate-500">10–2.000 karakter. Jelaskan bagian yang perlu diperbaiki, alasan penerusan, atau alasan penyelesaian. Catatan akan terlihat oleh Pelapor.</p>
                </div>
                <p class="muted">Keputusan dan catatan disimpan bersama. Keputusan selesai menutup proses laporan dan tidak dapat dibuka ulang.</p>
                <button type="submit" class="btn btn-primary">Simpan keputusan</button>
            </form>
        @endcan

        <x-report-details :report="$report" />
        <x-review-history :reviews="$reviews" />

        <x-related-reports :report="$report" :reports="$relatedReports" detail-route="relawan.reports.show" />
        <p class="muted mt-6">Peninjauan komunitas bukan verifikasi resmi kejadian. Status selesai menunjukkan proses dalam aplikasi telah selesai.</p>
    </div>
</x-layouts.public>
