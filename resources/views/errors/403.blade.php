<x-layouts.public title="Akses ditolak">
    <div class="container-page py-10 sm:py-14">
        <section class="panel max-w-xl" aria-labelledby="forbidden-title">
            <p class="mb-2 text-sm text-slate-500">403</p>
            <h1 id="forbidden-title" class="text-2xl font-semibold">Akses ditolak</h1>
            <p class="muted mt-3">Anda tidak memiliki kewenangan untuk membuka halaman ini.</p>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary mt-6">Kembali ke dashboard</a>
            @else
                <a href="{{ route('home') }}" class="btn btn-primary mt-6">Kembali ke beranda</a>
            @endauth
        </section>
    </div>
</x-layouts.public>
