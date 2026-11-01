<x-layouts.public title="Tentang aplikasi">
    <div class="container-page py-8 sm:py-10">
        <div class="page-heading">
            <p class="eyebrow"><a href="{{ route('home') }}" class="hover:underline">Beranda</a><span>/</span>Tentang aplikasi</p>
            <h1 class="section-title">Tentang Karhutla</h1>
            <p class="muted mt-2 max-w-3xl">Aplikasi komunitas untuk pelaporan, peninjauan, dan pemantauan informasi indikasi kebakaran hutan dan lahan.</p>
        </div>

        <section class="max-w-3xl" aria-labelledby="purpose-title">
            <h2 id="purpose-title" class="text-lg font-semibold">Tujuan aplikasi</h2>
            <p class="muted mt-2">Karhutla membantu masyarakat menyampaikan informasi kejadian secara terstruktur. Relawan meninjau kelengkapan informasi, sedangkan koordinator mencatat penerusan informasi kepada pihak terkait.</p>
        </section>

        <section class="mt-8" aria-labelledby="roles-title">
            <h2 id="roles-title" class="text-lg font-semibold">Pengguna dan kewenangan</h2>
            <div class="mt-4 overflow-x-auto rounded-md border border-slate-200" role="region" aria-label="Tabel peran pengguna, dapat digeser pada layar kecil" tabindex="0">
                <table class="w-full min-w-[560px] border-collapse text-left text-sm leading-7">
                    <caption class="sr-only">Rancangan pembagian fungsi pengguna pada aplikasi Karhutla</caption>
                    <thead class="bg-slate-50"><tr><th scope="col" class="w-52 px-5 py-3 font-semibold">Pengguna</th><th scope="col" class="px-5 py-3 font-semibold">Fungsi utama</th></tr></thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr><th scope="row" class="px-5 py-4 align-top font-medium">Pelapor</th><td class="px-5 py-4 text-slate-600">Membuat laporan, melengkapi lokasi dan bukti, memperbaiki saat diperbolehkan, serta memantau laporan miliknya.</td></tr>
                        <tr><th scope="row" class="px-5 py-4 align-top font-medium">Relawan Pemantau</th><td class="px-5 py-4 text-slate-600">Meninjau informasi, melihat laporan terkait pada wilayah sama, memberi catatan, dan menandai laporan yang perlu diteruskan.</td></tr>
                        <tr><th scope="row" class="px-5 py-4 align-top font-medium">Koordinator Relawan</th><td class="px-5 py-4 text-slate-600">Mengelola data pendukung dan kebutuhan akses relawan, melihat rekap, serta mencatat penerusan informasi kepada pihak terkait.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-8 max-w-3xl" aria-labelledby="limits-title">
            <h2 id="limits-title" class="text-lg font-semibold">Batasan aplikasi</h2>
            <ul class="muted mt-3 list-disc space-y-2 pl-5">
                <li>Peninjauan relawan bukan verifikasi resmi bahwa kebakaran benar-benar terjadi.</li>
                <li>Status selesai berarti proses dalam aplikasi selesai, bukan pernyataan bahwa api telah padam.</li>
                <li>Pihak terkait merupakan pihak eksternal dan tidak wajib memiliki akun aplikasi.</li>
                <li>Aplikasi ini tidak menjalankan operasi pemadaman atau layanan tanggap darurat.</li>
            </ul>
            <p class="mt-6 rounded-md bg-slate-50 p-4 text-sm leading-7 text-slate-600"><strong class="text-slate-700">Status pengembangan:</strong> halaman publik, login/logout, dashboard per peran, pengelolaan wilayah, pengiriman laporan, serta daftar/detail laporan milik sendiri sudah tersedia. Perbaikan laporan, lampiran bukti, peninjauan, dan rekap masih dalam pengembangan.</p>
        </section>
    </div>
</x-layouts.public>
