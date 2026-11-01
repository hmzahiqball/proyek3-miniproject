<x-layouts.public title="Data wilayah">
    <div class="container-page py-8 sm:py-10">
        <a href="{{ route('koordinator.dashboard') }}" class="text-sm text-forest-900 hover:underline">Kembali ke dashboard</a>
        <div class="page-heading mt-5 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="section-title">Data wilayah</h1>
                <p class="muted mt-2">Kelola wilayah yang dapat dipilih saat membuat laporan.</p>
            </div>
            <a href="{{ route('koordinator.areas.create') }}" class="btn btn-primary">Tambah wilayah</a>
        </div>

        @error('area')
            <p role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $message }}</p>
        @enderror

        <div class="space-y-3">
            @forelse($areas as $area)
                <section class="panel flex min-w-0 flex-col justify-between gap-4 sm:flex-row sm:items-start" aria-labelledby="area-{{ $area->id }}">
                    <div class="min-w-0">
                        <p class="break-all text-xs text-slate-500">{{ $area->code }}</p>
                        <h2 id="area-{{ $area->id }}" class="mt-1 break-words text-lg font-semibold">{{ $area->name }}</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            <span class="font-medium {{ $area->is_active ? 'text-forest-900' : 'text-slate-500' }}">{{ $area->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            <span class="mx-2" aria-hidden="true">·</span>{{ $area->reports_count }} laporan
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-start gap-3">
                        <a href="{{ route('koordinator.areas.edit', $area) }}" class="btn btn-outline" aria-label="Ubah {{ $area->name }}">Ubah</a>
                        @if($area->reports_count === 0)
                            <details class="max-w-64 text-sm">
                                <summary class="btn btn-outline text-red-700">Hapus</summary>
                                <form method="POST" action="{{ route('koordinator.areas.destroy', $area) }}" class="mt-3 rounded-md border border-slate-200 p-3">
                                    @csrf
                                    @method('DELETE')
                                    <p class="break-words leading-6">Hapus wilayah {{ $area->name }}? Tindakan ini tidak dapat dibatalkan.</p>
                                    <button type="submit" class="btn mt-3 bg-red-700 text-white hover:bg-red-800">Ya, hapus wilayah</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </section>
            @empty
                <p class="panel muted">Belum ada wilayah. Tambahkan wilayah pertama untuk kebutuhan pelaporan.</p>
            @endforelse
        </div>
        <p class="muted mt-6">Wilayah yang sudah dipakai laporan tidak dapat dihapus. Gunakan tombol Ubah untuk menonaktifkannya; laporan lama tetap tersimpan.</p>
    </div>
</x-layouts.public>
