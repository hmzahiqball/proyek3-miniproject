<x-layouts.public>
    <section class="border-b border-slate-200 bg-slate-50">
        <div class="container-page grid gap-8 py-10 md:grid-cols-[1.4fr_1fr] md:items-center md:py-14">
            <div>
                <h1 class="max-w-2xl text-3xl leading-tight font-semibold sm:text-4xl">Pelaporan Kebakaran<br class="hidden sm:block"> Hutan dan Lahan</h1>
                <p class="mt-4 max-w-xl text-base leading-7 text-slate-600">
                    Sampaikan indikasi kebakaran di sekitar Anda. Laporan akan ditinjau oleh relawan dan dapat diteruskan kepada pihak terkait.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">Buka dashboard</a>
                    @else
                        <a href="{{ route('access') }}" class="btn btn-primary">Masuk ke akun</a>
                    @endauth
                    <a href="{{ route('guide') }}" class="btn btn-outline">Panduan pelaporan</a>
                </div>
                <p class="mt-4 text-xs leading-6 text-slate-500">Pengiriman, peninjauan Relawan, kirim ulang, dan pencatatan penerusan oleh Koordinator sudah tersedia melalui dashboard.</p>
            </div>
            <aside class="panel" aria-labelledby="preparation-title">
                <h2 id="preparation-title" class="text-base font-semibold">Informasi yang perlu disiapkan</h2>
                <ul class="mt-4 space-y-3 text-sm text-slate-600">
                    @foreach(['Wilayah, petunjuk lokasi, dan koordinat.', 'Tanggal dan waktu pengamatan.', 'Deskripsi kondisi yang terlihat.', 'Bukti jika tersedia: JPG, PNG, atau PDF, maksimal 2 MB.'] as $item)
                        <li class="flex items-start gap-2">
                            <x-icon name="check" class="mt-0.5 size-4 text-forest-700" />
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </section>

    <div class="container-page py-9">
        <div class="rounded-md border-l-4 border-amber-600 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-950">
            <strong>Utamakan keselamatan.</strong> Jangan mendekati area berbahaya untuk mengambil bukti. Aplikasi ini bukan layanan tanggap darurat.
        </div>

        <section id="cara-kerja" class="py-9" aria-labelledby="process-title">
            <h2 id="process-title" class="text-xl font-semibold">Bagaimana laporan diproses?</h2>
            <div class="mt-6 grid gap-6 md:grid-cols-3">
                @foreach([
                    ['1', 'Buat laporan', 'Pelapor mengisi informasi kejadian, lokasi, dan bukti pendukung jika tersedia.'],
                    ['2', 'Peninjauan relawan', 'Relawan memeriksa kelengkapan informasi dan memberi catatan jika perlu diperbaiki atau diteruskan.'],
                    ['3', 'Pantau perkembangan', 'Pelapor melihat perkembangan laporannya. Koordinator mencatat penerusan informasi kepada pihak terkait.'],
                ] as [$number, $heading, $text])
                    <article class="border-t-2 border-slate-200 pt-4">
                        <h3 class="text-base font-semibold"><span class="mr-2 text-forest-700">{{ $number }}.</span>{{ $heading }}</h3>
                        <p class="muted mt-2">{{ $text }}</p>
                    </article>
                @endforeach
            </div>
            <p class="mt-6 text-xs leading-6 text-slate-500">Peninjauan relawan bukan verifikasi resmi kejadian. Status selesai hanya menunjukkan bahwa proses dalam aplikasi telah selesai.</p>
        </section>

        <section id="pertanyaan" class="grid gap-6 border-t border-slate-200 py-9 md:grid-cols-[1fr_2fr]" aria-labelledby="faq-title">
            <div>
                <h2 id="faq-title" class="text-xl font-semibold">Pertanyaan umum</h2>
                <p class="muted mt-2">Hal yang perlu diketahui sebelum membuat laporan.</p>
            </div>
            <div class="divide-y divide-slate-200 border-y border-slate-200">
                @foreach([
                    ['Apakah laporan harus sudah pasti kebakaran?', 'Tidak. Anda dapat melaporkan indikasi yang diamati. Tulis kondisi yang benar-benar terlihat dan bedakan dari dugaan. Relawan tidak menetapkan kejadian secara resmi.'],
                    ['Apakah bukti wajib disertakan?', 'Bukti bersifat pendukung. Satu laporan dapat memiliki satu file JPG/JPEG, PNG, atau PDF dengan ukuran maksimal 2 MB.'],
                    ['Siapa yang bisa melihat laporan saya?', 'Rancangan aplikasi membatasi akses pelapor pada laporan miliknya sendiri. Relawan dan koordinator mendapat akses sesuai kewenangan. Detail laporan pribadi tidak ditampilkan di halaman publik.'],
                    ['Apakah status selesai berarti api sudah padam?', 'Tidak. Selesai berarti proses laporan pada aplikasi komunitas selesai, bukan pernyataan resmi bahwa kebakaran telah padam.'],
                ] as [$question, $answer])
                    <details>
                        <summary class="flex min-h-14 items-center justify-between gap-4 py-4 text-sm font-medium">
                            {{ $question }}
                            <x-icon name="plus" class="faq-plus size-4" />
                        </summary>
                        <p class="muted pb-4 pr-6">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.public>
