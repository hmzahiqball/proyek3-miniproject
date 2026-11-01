<x-layouts.public title="Perubahan tidak dapat disimpan">
    <div class="container-page py-12">
        <p class="text-sm text-slate-500">409</p>
        <h1 class="section-title mt-3">Perubahan tidak dapat disimpan</h1>
        <p class="muted mt-4 max-w-2xl">{{ $exception->getMessage() ?: 'Data laporan sudah berubah. Muat ulang laporan sebelum mencoba lagi.' }}</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary mt-6">Kembali ke dashboard</a>
    </div>
</x-layouts.public>
