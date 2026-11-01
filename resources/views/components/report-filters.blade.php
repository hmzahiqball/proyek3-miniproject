@props(['areas', 'filters', 'action', 'total'])

@if($errors->any())
    <div role="alert" class="mb-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <p class="font-semibold">Filter belum diterapkan. Periksa pilihan Anda.</p>
        <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<form method="GET" action="{{ $action }}" class="panel mb-5" aria-label="Pencarian dan filter laporan">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="min-w-0 sm:col-span-2">
            <label for="report-search" class="block text-sm font-medium">Cari laporan</label>
            <input id="report-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="Judul atau petunjuk lokasi" class="field mt-2">
        </div>
        <div class="min-w-0">
            <label for="report-area" class="block text-sm font-medium">Wilayah</label>
            <select id="report-area" name="area_id" class="field mt-2">
                <option value="">Semua wilayah</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}" @selected((string) ($filters['area_id'] ?? '') === (string) $area->id)>{{ $area->name }}{{ ! $area->is_active ? ' (nonaktif)' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-0">
            <label for="report-status" class="block text-sm font-medium">Status</label>
            <select id="report-status" name="status" class="field mt-2">
                <option value="">Semua status</option>
                @foreach(\App\Enums\ReportStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <button type="submit" class="btn btn-primary">Terapkan filter</button>
        <a href="{{ $action }}" class="btn btn-outline">Reset filter</a>
        <p class="text-sm text-slate-500">{{ number_format($total, 0, ',', '.') }} laporan{{ $filters ? ' sesuai pencarian dan filter' : '' }}.</p>
    </div>
</form>
