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
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('koordinator.areas.index') }}" class="btn btn-primary">Kelola wilayah</a>
                        <a href="{{ route('koordinator.reports.index') }}" class="btn btn-outline">Koordinasi laporan</a>
                    </div>
                    <p class="muted mt-4">Lihat laporan dan tutup proses setelah penerusan tercatat. Pencatatan penerusan informasi akan tersedia pada tahap berikutnya.</p>
                @elseif(auth()->user()->role === \App\Enums\UserRole::Pelapor)
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('pelapor.reports.create') }}" class="btn btn-primary">Buat laporan</a>
                        <a href="{{ route('pelapor.reports.index') }}" class="btn btn-outline">Laporan saya</a>
                    </div>
                    <p class="muted mt-4">Anda dapat mengirim, melihat, dan memperbaiki laporan sendiri beserta bukti saat status mengizinkan. Kirim ulang tersedia setelah laporan diminta diperbaiki.</p>
                @else
                    <a href="{{ route('relawan.reports.index') }}" class="btn btn-primary mt-6">Pantau laporan</a>
                    <p class="muted mt-4">Mulai peninjauan, beri keputusan beserta catatan, dan lihat laporan terkait. Setiap tindakan mengikuti status laporan.</p>
                @endif
            </section>
        </div>
    </div>
</x-layouts.public>
