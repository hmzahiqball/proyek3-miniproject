<x-layouts.public title="Akun saya">
    <div class="container-page py-8 sm:py-10">
        <div class="page-heading">
            <h1 class="section-title">Akun saya</h1>
            <p class="muted mt-2">Selamat datang, {{ auth()->user()->name }}.</p>
        </div>

        <section class="panel max-w-2xl" aria-labelledby="account-title">
            <h2 id="account-title" class="text-lg font-semibold">Informasi akun</h2>
            <dl class="mt-5 grid gap-5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Email</dt>
                    <dd class="mt-1 break-words font-medium">{{ auth()->user()->email }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Peran pengguna</dt>
                    <dd class="mt-1 font-medium">{{ auth()->user()->role->label() }}</dd>
                </div>
            </dl>
            <p class="mt-6 rounded-md bg-slate-50 p-4 text-sm leading-7 text-slate-600">Anda sudah masuk. Fitur pengelolaan laporan untuk setiap peran masih dalam pengembangan.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('guide') }}" class="btn btn-outline">Panduan pelaporan</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Keluar dari akun</button>
                </form>
            </div>
        </section>
    </div>
</x-layouts.public>
