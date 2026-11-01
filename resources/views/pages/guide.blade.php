<x-layouts.public title="Panduan pelaporan">
    <div class="container-page py-8 sm:py-10">
        <div class="page-heading">
            <p class="eyebrow"><a href="{{ route('home') }}" class="hover:underline">Beranda</a><span>/</span>Panduan</p>
            <h1 class="section-title">Panduan pelaporan</h1>
            <p class="muted mt-2">Siapkan informasi berikut sebelum membuat laporan indikasi kebakaran.</p>
        </div>

        <div class="grid items-start gap-8 lg:grid-cols-[1fr_300px]">
            <div>
                <p class="mb-6 rounded-md bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                    <strong>Perhatikan keselamatan.</strong> Jangan mendekati api atau asap untuk mengambil foto. Jika ada bahaya langsung, cari tempat aman dan hubungi pihak berwenang setempat.
                </p>
                <ol class="divide-y divide-slate-200">
                    @foreach([
                        ['Lokasi kejadian', 'Tentukan wilayah dan tambahkan petunjuk lokasi. Siapkan koordinat dari aplikasi peta yang Anda gunakan.', 'Latitude: −90 sampai 90. Longitude: −180 sampai 180.'],
                        ['Waktu pengamatan', 'Catat tanggal dan waktu saat Anda melihat indikasi kejadian.', 'Gunakan waktu pengamatan, bukan hanya waktu mengisi formulir.'],
                        ['Kondisi yang terlihat', 'Tuliskan apa yang Anda amati secara singkat dan jelas. Hindari menyimpulkan hal yang belum diketahui.', 'Contoh: Terlihat asap dari lahan di sebelah timur jalan.'],
                        ['Bukti pendukung', 'Siapkan satu file jika tersedia. Hindari informasi pribadi yang tidak diperlukan.', 'Format JPG/JPEG, PNG, atau PDF. Ukuran maksimal 2 MB.'],
                        ['Periksa laporan', 'Periksa kembali informasi sebelum dikirim. Setelah fitur tersedia, perkembangan dapat dilihat melalui akun pelapor.', 'Perbaikan laporan mengikuti status yang mengizinkan.'],
                    ] as [$heading, $text, $hint])
                        <li class="flex gap-4 py-5 first:pt-0">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded bg-slate-100 text-sm text-slate-600">{{ $loop->iteration }}</span>
                            <div>
                                <h2 class="text-base font-semibold">{{ $heading }}</h2>
                                <p class="muted mt-1">{{ $text }}</p>
                                <p class="mt-2 text-xs leading-6 text-slate-500">{{ $hint }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <aside id="persiapan" class="panel bg-slate-50" aria-labelledby="checklist-title">
                <h2 id="checklist-title" class="text-base font-semibold">Checklist persiapan</h2>
                <p class="muted mt-2">Tandai informasi yang sudah siap.</p>
                <div data-report-checklist class="mt-4 space-y-3">
                    @foreach(['Wilayah dan petunjuk lokasi', 'Latitude dan longitude', 'Tanggal dan waktu kejadian', 'Deskripsi kondisi yang diamati', 'Bukti, jika tersedia'] as $item)
                        <label class="flex cursor-pointer items-start gap-3 py-1 text-sm leading-6">
                            <input type="checkbox" class="mt-1 size-4 shrink-0 accent-forest-900">
                            <span>{{ $item }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-5 h-1.5 overflow-hidden rounded bg-slate-200" aria-hidden="true">
                    <div data-checklist-bar class="h-full bg-forest-700 transition-all" style="width:0%"></div>
                </div>
                <p data-checklist-progress role="status" aria-live="polite" class="mt-3 text-xs text-slate-600">0 dari 5 persiapan ditandai</p>
                <button type="button" data-checklist-reset class="mt-4 min-h-11 text-sm text-forest-700 underline underline-offset-4">Kosongkan checklist</button>
                <p class="mt-4 border-t border-slate-200 pt-4 text-xs leading-6 text-slate-500">Checklist tidak mengirim laporan dan akan kosong kembali saat halaman dimuat ulang.</p>
            </aside>
        </div>
        <p class="mt-8 border-t border-slate-200 pt-5 text-sm text-slate-600">Sudah memahami panduannya? <a href="{{ route('access') }}" class="font-medium text-forest-700 underline underline-offset-4">Lihat halaman masuk</a>.</p>
    </div>
</x-layouts.public>
