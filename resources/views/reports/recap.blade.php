<x-layouts.public title="Rekap laporan">
    <div class="container-page py-8 sm:py-10">
        <a href="{{ auth()->user()->role === \App\Enums\UserRole::Koordinator ? route('koordinator.dashboard') : route('relawan.dashboard') }}" class="text-sm text-forest-900 hover:underline">Kembali ke dashboard</a>
        <div class="page-heading mt-5">
            <h1 class="section-title">Rekap laporan</h1>
            <p class="muted mt-2">Jumlah laporan dari seluruh Pelapor, dikelompokkan berdasarkan wilayah.</p>
        </div>

        @if($errors->any())
            <div role="alert" class="mb-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Filter belum diterapkan. Periksa pilihan wilayah.</p>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="GET" action="{{ route('reports.recap') }}" class="panel mb-6" aria-label="Filter rekap laporan">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="min-w-0 sm:w-80">
                    <label for="recap-area" class="block text-sm font-medium">Wilayah</label>
                    <select id="recap-area" name="area_id" class="field mt-2">
                        <option value="">Semua wilayah</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}" @selected((string) $areaId === (string) $area->id)>{{ $area->name }}{{ ! $area->is_active ? ' (nonaktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn btn-primary">Terapkan filter</button>
                    <a href="{{ route('reports.recap') }}" class="btn btn-outline">Reset filter</a>
                </div>
            </div>
        </form>

        <section aria-label="Ringkasan laporan">
            <dl class="grid gap-4 sm:grid-cols-3">
                @foreach(['total' => 'Total laporan', 'active' => 'Laporan aktif', 'completed' => 'Laporan selesai'] as $key => $label)
                    <div class="panel min-w-0">
                        <dt class="text-sm text-slate-500">{{ $label }}</dt>
                        <dd class="mt-3 text-3xl font-semibold text-forest-900">{{ number_format($summary[$key], 0, ',', '.') }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-3 text-xs leading-6 text-slate-500">Laporan aktif mencakup semua laporan yang belum berstatus Selesai. Ringkasan dan tabel mengikuti pilihan wilayah yang sama.</p>
        </section>

        @if($summary['total'] === 0)
            <p role="status" class="mt-6 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">{{ $areaId ? 'Belum ada laporan di wilayah yang dipilih.' : 'Belum ada laporan untuk direkap.' }}</p>
        @endif
        <section class="panel mt-6 min-w-0" aria-labelledby="recap-table-title">
            <h2 id="recap-table-title" class="text-lg font-semibold">Laporan per wilayah</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[28rem] text-sm">
                    <caption class="sr-only">Jumlah total, aktif, dan selesai per wilayah sesuai filter.</caption>
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th scope="col" class="px-3 py-3 text-left font-medium">Wilayah</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Total</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Aktif</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="border-b border-slate-100">
                                <th scope="row" class="max-w-xs break-words px-3 py-4 text-left font-medium">{{ $row->name }} @if(! $row->is_active)<span class="text-xs font-normal text-slate-500">(nonaktif)</span>@endif</th>
                                <td class="px-3 py-4 text-right tabular-nums">{{ number_format($row->total_reports, 0, ',', '.') }}</td>
                                <td class="px-3 py-4 text-right tabular-nums">{{ number_format($row->active_reports, 0, ',', '.') }}</td>
                                <td class="px-3 py-4 text-right tabular-nums">{{ number_format($row->completed_reports, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-4 text-slate-500">Belum ada data wilayah.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200 font-semibold">
                            <th scope="row" class="px-3 py-4 text-left">Total sesuai filter</th>
                            <td class="px-3 py-4 text-right tabular-nums">{{ number_format($summary['total'], 0, ',', '.') }}</td>
                            <td class="px-3 py-4 text-right tabular-nums">{{ number_format($summary['active'], 0, ',', '.') }}</td>
                            <td class="px-3 py-4 text-right tabular-nums">{{ number_format($summary['completed'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
        <p class="muted mt-6">Status Selesai menunjukkan proses dalam aplikasi telah selesai, bukan pernyataan bahwa api telah padam.</p>
    </div>
</x-layouts.public>
