<x-layouts.public title="Panduan pelaporan" description="Panduan menyiapkan lokasi, waktu, kondisi, dan bukti untuk laporan indikasi kebakaran hutan dan lahan.">
    <section class="container-page pt-12 pb-14 lg:pt-18">
        <p class="eyebrow"><a href="{{ route('home') }}" class="hover:underline">Beranda</a><span>/</span>Panduan pelaporan</p>
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end"><h1 class="section-title max-w-xl !text-4xl sm:!text-5xl lg:!text-6xl">Laporan yang jelas.<br>Langkah yang terarah.</h1><p class="muted max-w-sm">Siapkan informasi sesuai pengamatan Anda. Panduan ini membantu Anda mengetahui apa saja yang perlu dicatat.</p></div>
    </section>
    <section class="container-page pb-20">
        <div class="grid gap-10 lg:grid-cols-[1fr_340px] lg:items-start lg:gap-14">
            <div>
                <div class="mb-9 flex gap-4 rounded-2xl border border-[#e8c3a1] bg-[#fff4e8] p-5"><x-icon name="shield" class="mt-1 text-[#9b5425]" /><div><h2 class="text-sm font-semibold text-[#86451f]">Keselamatan selalu lebih dulu</h2><p class="mt-2 text-sm leading-7 text-[#86451f]">Jangan mendekati api atau asap untuk mengambil bukti. Jika ada bahaya langsung, cari tempat aman dan hubungi pihak berwenang setempat.</p></div></div>
                @foreach([
                    ['01', 'Tentukan lokasi kejadian', 'Pilih wilayah tempat kejadian, tambahkan petunjuk lokasi, lalu siapkan latitude dan longitude. Koordinat dapat diperoleh dari aplikasi peta yang Anda gunakan.', 'Latitude: −90 hingga 90 · Longitude: −180 hingga 180', 'pin'],
                    ['02', 'Ceritakan kondisi yang terlihat', 'Tulis ringkasan dan deskripsi pengamatan: misalnya asap yang tampak, api yang terlihat, atau kondisi lahan di sekitar. Bedakan pengamatan dengan dugaan.', 'Contoh: Terlihat asap dari arah lahan di sebelah timur jalan.', 'file'],
                    ['03', 'Catat waktu pengamatan', 'Cantumkan tanggal dan waktu saat Anda melihat indikasi kejadian. Waktu pengamatan membantu relawan memahami konteks laporan.', 'Gunakan waktu kejadian, bukan hanya waktu mengisi formulir.', 'clock'],
                    ['04', 'Siapkan bukti bila tersedia', 'Satu laporan dapat dilengkapi satu file pendukung. Pilih bukti yang relevan dan hindari menyertakan informasi pribadi yang tidak diperlukan.', 'JPG/JPEG, PNG, atau PDF · Maksimal 2 MB', 'camera'],
                    ['05', 'Periksa dan pantau laporan', 'Setelah fitur pelaporan tersedia, masuk ke akun pelapor untuk mengirim informasi dan melihat perkembangannya. Perbaikan mengikuti status laporan yang mengizinkan.', 'Saat ini pengiriman laporan dan akun masih dalam pengembangan.', 'eye']
                ] as [$number, $heading, $text, $hint, $icon])
                    <article class="flex gap-5 border-b border-forest-900/10 py-8 first:pt-0"><span class="pt-1 text-xs text-forest-700">{{ $number }}</span><div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-3"><h2 class="text-xl font-medium tracking-tight">{{ $heading }}</h2><x-icon :name="$icon" class="text-forest-700" /></div><p class="muted mt-3">{{ $text }}</p><p class="mt-4 border-l-2 border-forest-700/35 pl-4 text-xs leading-6 text-forest-700">{{ $hint }}</p></div></article>
                @endforeach
            </div>
            <aside id="persiapan" class="rounded-3xl border border-forest-900/10 bg-white p-7 lg:sticky lg:top-28" aria-labelledby="checklist-title">
                <p class="eyebrow">Checklist pribadi</p><h2 id="checklist-title" class="text-2xl font-medium tracking-tight">Sudah siap melapor?</h2><p class="muted mt-3">Tandai informasi yang sudah Anda siapkan. Checklist ini tidak mengirim laporan.</p>
                <div data-report-checklist class="mt-6 space-y-3">
                    @foreach(['Wilayah dan petunjuk lokasi', 'Latitude dan longitude', 'Tanggal dan waktu kejadian', 'Deskripsi kondisi yang diamati', 'Bukti, jika tersedia'] as $item)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-paper p-3.5 text-sm leading-6"><input type="checkbox" class="mt-1 size-4 shrink-0 accent-forest-900" /> <span>{{ $item }}</span></label>
                    @endforeach
                </div>
                <div class="mt-6 h-1.5 overflow-hidden rounded-full bg-forest-100" aria-hidden="true"><div data-checklist-bar class="h-full rounded-full bg-forest-700 transition-all" style="width:0%"></div></div>
                <p data-checklist-progress role="status" aria-live="polite" class="mt-3 text-xs text-forest-700">0 dari 5 persiapan ditandai</p>
                <button type="button" data-checklist-reset class="mt-5 text-xs underline underline-offset-4 hover:text-forest-700">Kosongkan checklist</button>
                <p class="mt-5 border-t border-forest-900/10 pt-4 text-[11px] leading-5 text-forest-950/50">Checklist hanya berlaku selama halaman ini terbuka dan akan direset saat dimuat ulang.</p>
            </aside>
        </div>
    </section>
    <section class="bg-forest-100/60 py-14"><div class="container-page flex flex-col justify-between gap-6 md:flex-row md:items-center"><div><h2 class="text-2xl font-medium tracking-tight">Pahami peran di balik setiap laporan.</h2><p class="muted mt-2">Masyarakat, relawan, dan koordinator saling melengkapi.</p></div><a href="{{ route('about') }}" class="btn btn-primary self-start">Tentang Karhutla <x-icon class="size-4" /></a></div></section>
</x-layouts.public>
