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

        @if($errors->any())
            <div role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Laporan belum dapat dikirim ulang. Perbaiki data berikut terlebih dahulu.</p>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @can('resubmit', $report)
            <form method="POST" action="{{ route('pelapor.reports.resubmit', $report) }}" class="panel mb-6">
                @csrf
                <h2 class="text-lg font-semibold">Kirim ulang untuk ditinjau</h2>
                <p class="muted mt-2 mb-4">Baca catatan Relawan dan simpan perbaikan terlebih dahulu. Setelah dikirim ulang, laporan kembali ditinjau dan tidak dapat diedit sementara.</p>
                <button type="submit" class="btn btn-primary">Kirim ulang laporan</button>
            </form>
        @endcan

        <x-report-details :report="$report" />
        <x-review-history :reviews="$reviews" />
        <p class="muted mt-6">Status menunjukkan proses dalam aplikasi. Status selesai bukan pernyataan bahwa api telah padam.</p>
    </div>
</x-layouts.public>
