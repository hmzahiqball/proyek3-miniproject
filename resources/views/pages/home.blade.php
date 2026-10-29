<x-layouts.public>
    <section class="container-page pt-8 pb-14 lg:pt-10 lg:pb-20">
        <div class="grid gap-10 lg:min-h-[620px] lg:grid-cols-[1.05fr_1fr] lg:gap-12">
            <div class="flex flex-col justify-center py-5 lg:py-10">
                <p class="eyebrow"><span class="size-1.5 rounded-full bg-forest-700"></span> Bersama menjaga ruang hidup</p>
                <h1 class="max-w-xl text-[clamp(3rem,6vw,5.2rem)] leading-[1.05] font-medium tracking-[-0.065em]">Hutan terjaga.<br>Kepedulian<br><span class="text-forest-700">jadi tindakan.</span></h1>
                <p class="mt-7 max-w-md text-base leading-8 text-forest-950/65">Lihat indikasi kebakaran hutan atau lahan? Sampaikan informasi dari sekitar Anda, bantu relawan meninjau, dan ikuti perkembangannya.</p>
                <div class="mt-8 flex flex-wrap gap-3"><a href="#mulai" class="btn btn-primary">Mulai dari laporan Anda <x-icon class="size-4" /></a><a href="#cara-kerja" class="btn btn-outline">Pelajari caranya</a></div>
                <div class="mt-10 flex items-center gap-3 border-t border-forest-900/10 pt-6 text-xs leading-5 text-forest-950/60"><x-icon name="shield" class="size-8 text-forest-700" /><p>Informasi yang terarah.<br><span class="font-medium text-forest-950">Kepedulian yang saling terhubung.</span></p></div>
            </div>
            <div class="relative min-h-[470px] overflow-hidden rounded-[28px] bg-forest-900 lg:min-h-[620px]">
                <img src="{{ asset('images/forest-landscape.svg') }}" alt="Ilustrasi pegunungan dan hutan dengan penanda lokasi pengamatan" class="absolute inset-0 size-full object-cover" width="700" height="800" fetchpriority="high">
                <div class="absolute top-6 right-6 left-6 flex items-center justify-between text-[10px] font-medium tracking-[0.16em] text-white/90"><span class="rounded-full border border-white/30 bg-forest-900/20 px-3 py-2 backdrop-blur">DARI SEKITAR, UNTUK BERSAMA</span><x-icon name="leaf" class="size-5" /></div>
                <div class="absolute right-5 bottom-5 left-5 rounded-2xl border border-white/20 bg-forest-950/70 p-5 text-white backdrop-blur-md sm:p-6">
                    <div class="flex items-start gap-4"><span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-ember/20 text-[#ffc294]"><x-icon name="pin" /></span><div><p class="text-lg font-medium tracking-tight">Setiap lokasi punya cerita.</p><p class="mt-1 text-xs leading-6 text-white/70">Pengamatan Anda membantu informasi sampai kepada orang yang tepat.</p></div></div>
                    <div class="mt-5 flex items-center justify-between border-t border-white/15 pt-4 text-[10px] tracking-wide text-white/60"><span>AMATI · LAPORKAN · PANTAU</span><span>Ilustrasi, bukan data kejadian</span></div>
                </div>
            </div>
        </div>
    </section>

    <div class="border-y border-forest-900/10 bg-forest-100/60">
        <div class="container-page grid gap-5 py-6 sm:grid-cols-3">
            @foreach([['pin', 'Lokasi yang lebih jelas', 'Wilayah dan koordinat kejadian'], ['file', 'Informasi yang terstruktur', 'Kondisi, waktu, dan bukti pendukung'], ['eye', 'Perkembangan yang terlacak', 'Ikuti proses laporan milik Anda']] as [$icon, $heading, $text])
                <div class="flex items-center gap-4"><x-icon :name="$icon" class="size-6 text-forest-700" /><div><p class="text-sm font-semibold">{{ $heading }}</p><p class="mt-1 text-xs text-forest-950/60">{{ $text }}</p></div></div>
            @endforeach
        </div>
    </div>

    <section id="cara-kerja" class="container-page py-20 lg:py-25">
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end"><div><p class="eyebrow">01 / Cara kerja</p><h2 class="section-title">Satu laporan.<br>Langkah yang berarti.</h2></div><p class="muted max-w-sm">Dari pengamatan masyarakat hingga tindak lanjut informasi, setiap langkah punya peran.</p></div>
        <div class="mt-12 grid gap-7 md:grid-cols-3">
            @foreach([['01', 'Amati dan laporkan', 'Ceritakan kondisi yang Anda lihat. Lengkapi wilayah, koordinat, waktu, dan bukti jika tersedia.', 'file'], ['02', 'Relawan meninjau', 'Relawan memeriksa kelengkapan informasi, memberi catatan, dan melihat laporan terkait di wilayah yang sama.', 'users'], ['03', 'Ikuti perkembangannya', 'Pantau laporan Anda. Koordinator dapat mencatat penerusan informasi kepada pihak terkait.', 'eye']] as [$number, $heading, $text, $icon])
                <article class="border-t border-forest-900/20 pt-6"><div class="mb-9 flex items-center justify-between"><span class="text-xs text-forest-700">/ {{ $number }}</span><x-icon :name="$icon" class="size-7 text-forest-700" /></div><h3 class="text-xl font-medium tracking-tight">{{ $heading }}</h3><p class="muted mt-3">{{ $text }}</p></article>
            @endforeach
        </div>
    </section>

    <section id="tentang" class="bg-forest-900 py-18 text-white lg:py-22">
        <div class="container-page grid items-start gap-12 lg:grid-cols-[1fr_1.1fr] lg:gap-20">
            <div><p class="eyebrow text-[#bbd2b7]">02 / Kepedulian bersama</p><h2 class="section-title">Hutan bukan hanya<br>sekumpulan pohon.<br><span class="text-[#bbd2b7]">Ia ruang hidup kita.</span></h2><p class="mt-6 max-w-sm text-sm leading-7 text-white/65">Karhutla menghubungkan pengamatan masyarakat dengan peninjauan relawan. Informasi yang rapi membantu proses pemantauan menjadi lebih jelas.</p><a href="#mulai" class="mt-8 inline-flex items-center gap-4 border-b border-white/35 pb-2 text-sm">Ambil bagian dari sekitar Anda <x-icon class="size-4" /></a></div>
            <div class="divide-y divide-white/15">
                @foreach([['01', 'Masyarakat sebagai pelapor', 'Mengirim informasi kejadian dan memantau perkembangan laporannya.'], ['02', 'Relawan sebagai pemantau', 'Meninjau informasi dan memberi catatan sesuai kewenangannya.'], ['03', 'Koordinator sebagai penghubung', 'Mengelola data pendukung dan mencatat penerusan informasi.']] as [$number, $heading, $text])
                    <div class="flex gap-6 py-6 first:pt-0 last:pb-0"><span class="pt-1 text-xs text-[#bbd2b7]">{{ $number }}</span><div><h3 class="text-lg font-medium tracking-tight">{{ $heading }}</h3><p class="mt-2 text-sm leading-7 text-white/60">{{ $text }}</p></div></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="container-page grid gap-10 py-20 lg:grid-cols-2 lg:items-center lg:gap-20">
        <div><p class="eyebrow">03 / Sebelum melapor</p><h2 class="section-title">Informasi yang baik<br>dimulai dari persiapan.</h2><p class="muted mt-5 max-w-md">Utamakan keselamatan. Catat apa yang dapat diamati tanpa mendekati area berbahaya.</p><a href="#mulai" class="btn btn-outline mt-7">Siapkan laporan Anda <x-icon class="size-4" /></a></div>
        <div class="rounded-3xl bg-[#f0f2e9] p-7 sm:p-9">
            @foreach([['pin', 'Di mana lokasinya?', 'Siapkan wilayah, petunjuk lokasi, dan koordinat.'], ['clock', 'Kapan dan apa yang terlihat?', 'Catat waktu pengamatan dan jelaskan kondisi.'], ['camera', 'Ada bukti pendukung?', 'Satu JPG, PNG, atau PDF, maksimal 2 MB.']] as [$icon, $heading, $text])
                <div class="flex gap-4 py-4 first:pt-0 last:pb-0"><span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-forest-700"><x-icon :name="$icon" /></span><div><h3 class="text-sm font-semibold">{{ $heading }}</h3><p class="mt-1 text-xs leading-6 text-forest-950/65">{{ $text }}</p></div></div>
            @endforeach
        </div>
    </section>

    <section id="pertanyaan" class="container-page border-t border-forest-900/10 py-20">
        <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20"><div><p class="eyebrow">04 / Pertanyaan umum</p><h2 class="section-title">Kenali dulu,<br>lalu ambil peran.</h2><p class="muted mt-5 max-w-xs">Beberapa hal yang perlu Anda ketahui tentang pelaporan di Karhutla.</p></div><div>
            @foreach([
                ['Apakah Karhutla layanan tanggap darurat?', 'Karhutla adalah aplikasi komunitas untuk pelaporan dan pemantauan informasi. Jika ada bahaya langsung, utamakan keselamatan dan hubungi pihak berwenang setempat.'],
                ['Apakah saya harus yakin telah terjadi kebakaran?', 'Laporan dapat berisi indikasi yang Anda amati, seperti asap atau api. Jelaskan sesuai pengamatan, tanpa menyimpulkan hal yang belum diketahui. Peninjauan relawan bukan verifikasi resmi kejadian.'],
                ['Siapa yang dapat melihat laporan saya?', 'Pada rancangan aplikasi, pelapor hanya dapat mengakses laporan miliknya. Relawan dan koordinator mengakses laporan sesuai kewenangan. Halaman publik tidak menampilkan identitas atau detail pribadi pelapor.'],
                ['Apa arti laporan berstatus selesai?', 'Selesai berarti proses laporan dalam aplikasi komunitas telah selesai. Status tersebut bukan pernyataan resmi bahwa kebakaran telah padam.'],
                ['Apakah saya sudah bisa mengirim laporan?', 'Saat ini Karhutla berada pada tahap pengembangan antarmuka publik. Login, pengiriman laporan, dan dashboard setiap peran akan tersedia pada tahap berikutnya.']
            ] as [$question, $answer])
                <details class="group border-b border-forest-900/15 first:border-t"><summary class="flex min-h-19 items-center justify-between gap-5 py-5 text-sm font-medium">{{ $question }}<x-icon name="plus" class="faq-plus size-4" /></summary><p class="muted pr-7 pb-6">{{ $answer }}</p></details>
            @endforeach
        </div></div>
    </section>

    <section id="mulai" class="container-page pb-20">
        <div class="relative overflow-hidden rounded-[28px] bg-[#e5ecd9] p-8 sm:p-12 lg:p-14">
            <x-icon name="leaf" class="pointer-events-none absolute -right-8 -bottom-16 size-72 rotate-[-25deg] text-forest-700/8" />
            <div class="relative max-w-2xl"><p class="eyebrow">Langkah kecil, kepedulian besar</p><h2 class="section-title">Mulai dari yang Anda lihat.<br>Bantu hutan tetap terjaga.</h2><p class="muted mt-5 max-w-lg">Kenali informasi yang perlu disiapkan sebelum membuat laporan. Fitur pengiriman laporan sedang dikembangkan.</p><a href="#cara-kerja" class="btn btn-primary mt-7">Lihat alur pelaporan <x-icon class="size-4" /></a></div>
        </div>
    </section>
</x-layouts.public>
