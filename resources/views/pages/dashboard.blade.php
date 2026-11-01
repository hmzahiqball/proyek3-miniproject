<x-layouts.public :title="$title">
    <div class="container-page py-8 sm:py-10">
        <div class="page-heading">
            <h1 class="section-title">{{ $title }}</h1>
            <p class="muted mt-2">Selamat datang, {{ auth()->user()->name }}.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="panel min-w-0" aria-labelledby="account-title">
                <h2 id="account-title" class="text-lg font-semibold">Informasi akun</h2>
                <dl class="mt-5 space-y-5 text-sm">
                    <div>
                        <dt class="text-slate-500">Email</dt>
                        <dd class="mt-1 break-all font-medium">{{ auth()->user()->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Peran pengguna</dt>
                        <dd class="mt-1 font-medium">{{ auth()->user()->role->label() }}</dd>
                    </div>
                </dl>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('guide') }}" class="btn btn-outline">Panduan pelaporan</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Keluar dari akun</button>
                    </form>
                </div>
            </section>

            <section class="panel min-w-0" aria-labelledby="role-title">
                <h2 id="role-title" class="text-lg font-semibold">Kegiatan sesuai peran</h2>
                <ul class="muted mt-4 list-disc space-y-2 pl-5">
                    @foreach($responsibilities as $responsibility)
                        <li>{{ $responsibility }}</li>
                    @endforeach
                </ul>
                @if(auth()->user()->role === \App\Enums\UserRole::Koordinator)
                    <a href="{{ route('koordinator.areas.index') }}" class="btn btn-primary mt-6">Kelola wilayah</a>
                    <p class="muted mt-4">Pengelolaan wilayah sudah tersedia. Fitur proses laporan akan ditambahkan pada tahap berikutnya.</p>
                @elseif(auth()->user()->role === \App\Enums\UserRole::Pelapor)
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('pelapor.reports.create') }}" class="btn btn-primary">Buat laporan</a>
                        <a href="{{ route('pelapor.reports.index') }}" class="btn btn-outline">Laporan saya</a>
                    </div>
                    <p class="muted mt-4">Anda dapat mengirim dan melihat laporan sendiri. Perbaikan laporan, lampiran bukti, dan peninjauan akan ditambahkan pada tahap berikutnya.</p>
                @else
                    <p class="mt-6 rounded-md bg-slate-50 p-4 text-sm leading-7 text-slate-600">Fitur operasional di atas sedang disiapkan dan belum dapat digunakan.</p>
                @endif
            </section>
        </div>
    </div>
</x-layouts.public>
