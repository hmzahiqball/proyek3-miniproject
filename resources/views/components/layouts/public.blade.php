@props(['title' => 'Jaga hutan, mulai dari kepedulian.', 'description' => 'Karhutla membantu masyarakat menyampaikan dan memantau laporan indikasi kebakaran hutan dan lahan.'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    <meta name="theme-color" content="#173d32">
    <title>{{ $title }} — Karhutla</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/karhutla-mark.svg') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#konten" class="fixed top-3 left-3 z-50 -translate-y-24 rounded-full bg-forest-900 px-5 py-3 text-sm text-white focus:translate-y-0">Lewati ke konten</a>
    <header class="sticky top-0 z-40 border-b border-forest-900/8 bg-paper/95 backdrop-blur-xl">
        <div class="container-page flex h-21 items-center justify-between gap-5">
            <a href="{{ route('home') }}" aria-label="Karhutla — Beranda" class="flex items-center gap-2.5">
                <span class="flex size-10 items-center justify-center rounded-xl bg-forest-900 text-white"><x-icon name="leaf" class="size-6" /></span>
                <span class="text-[23px] font-semibold tracking-[-0.07em]">karhutla<span class="text-ember">.</span></span>
            </a>
            <nav aria-label="Navigasi utama" class="hidden items-center gap-1 md:flex">
                <a class="nav-link" href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                <a class="nav-link" href="{{ route('home') }}#cara-kerja">Cara kerja</a>
                <a class="nav-link" href="{{ route('home') }}#tentang">Tentang kami</a>
                <a class="nav-link" href="{{ route('home') }}#pertanyaan">FAQ</a>
            </nav>
            <a href="{{ route('home') }}#mulai" class="btn btn-primary hidden sm:inline-flex">Mulai melapor <x-icon class="size-4" /></a>
            <button type="button" data-menu-toggle aria-expanded="false" aria-controls="menu-mobile" aria-label="Buka menu navigasi" class="flex size-11 items-center justify-center rounded-full border border-forest-900/15 md:hidden"><x-icon name="menu" /></button>
        </div>
        <nav id="menu-mobile" data-mobile-menu hidden aria-label="Navigasi seluler" class="container-page border-t border-forest-900/10 py-4 md:hidden">
            <div class="flex flex-col gap-1">
                <a class="nav-link" href="{{ route('home') }}">Beranda</a>
                <a class="nav-link" href="{{ route('home') }}#cara-kerja">Cara kerja</a>
                <a class="nav-link" href="{{ route('home') }}#tentang">Tentang kami</a>
                <a class="nav-link" href="{{ route('home') }}#pertanyaan">FAQ</a>
                <a class="btn btn-primary mt-3" href="{{ route('home') }}#mulai">Mulai melapor <x-icon /></a>
            </div>
        </nav>
    </header>
    <main id="konten">{{ $slot }}</main>
    <footer class="border-t border-forest-900/10 bg-[#f0f3eb] py-12">
        <div class="container-page flex flex-col justify-between gap-9 md:flex-row">
            <div class="max-w-sm">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-semibold tracking-tight"><x-icon name="leaf" /> karhutla<span class="-ml-2 text-ember">.</span></a>
                <p class="muted mt-4">Ruang kepedulian bersama untuk hutan dan lahan yang lebih terjaga.</p>
            </div>
            <div class="flex flex-wrap gap-14 text-sm">
                <div><p class="mb-4 font-semibold">Jelajahi</p><div class="flex flex-col gap-3 text-forest-950/65"><a href="{{ route('home') }}#cara-kerja" class="hover:underline">Cara kerja</a><a href="{{ route('home') }}#tentang" class="hover:underline">Tentang Karhutla</a><a href="{{ route('home') }}#pertanyaan" class="hover:underline">Pertanyaan umum</a></div></div>
                <div class="max-w-52"><p class="mb-4 font-semibold">Tentang platform</p><p class="text-xs leading-6 text-forest-950/60">Proyek komunitas pelaporan dan pemantauan informasi. Bukan layanan tanggap darurat.</p></div>
            </div>
        </div>
        <div class="container-page mt-10 flex flex-col justify-between gap-3 border-t border-forest-900/10 pt-6 text-xs text-forest-950/55 sm:flex-row"><p>© {{ date('Y') }} Karhutla. Tumbuh dari kepedulian.</p><p>Mini Project Proyek 3 · Antarmuka publik</p></div>
    </footer>
</body>
</html>
