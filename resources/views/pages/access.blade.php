<x-layouts.public title="Masuk">
    <div class="container-page py-10 sm:py-14">
        <section class="mx-auto max-w-md rounded-lg border border-slate-200 p-6 sm:p-8" aria-labelledby="login-title">
            <h1 id="login-title" class="text-2xl font-semibold">Masuk</h1>
            <p class="muted mt-2">Gunakan akun Anda untuk mengakses pelaporan.</p>

            <p id="access-note" class="mt-5 rounded-md bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                <strong>Login belum tersedia.</strong> Halaman ini masih berupa rancangan tampilan. Formulir akan diaktifkan setelah autentikasi selesai dibuat.
            </p>

            <fieldset disabled aria-describedby="access-note" class="mt-6 space-y-5">
                <legend class="sr-only">Rancangan formulir masuk yang belum aktif</legend>
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium">Email</label>
                    <input id="email" type="email" autocomplete="off" placeholder="nama@contoh.id" class="field cursor-not-allowed">
                </div>
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium">Kata sandi</label>
                    <input id="password" type="password" autocomplete="off" placeholder="Kata sandi" class="field cursor-not-allowed">
                </div>
                <button type="button" class="btn w-full cursor-not-allowed bg-slate-200 text-slate-500">Masuk</button>
            </fieldset>

            <p class="mt-5 text-xs leading-6 text-slate-500">Akses pelapor, relawan, dan koordinator akan ditentukan dari akun yang digunakan.</p>
            <a href="{{ route('guide') }}" class="mt-6 inline-flex min-h-11 items-center text-sm text-forest-700 underline underline-offset-4">Baca panduan pelaporan</a>
        </section>
    </div>
</x-layouts.public>
