@props([
    'title' => 'Pelaporan kebakaran hutan dan lahan',
    'description' => 'Aplikasi komunitas untuk pelaporan dan pemantauan indikasi kebakaran hutan dan lahan.',
])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    <meta name="theme-color" content="#285443">
    <title>{{ $title }} | Karhutla</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/karhutla-mark.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <a href="#konten" class="fixed top-3 left-3 z-50 -translate-y-24 rounded bg-forest-900 px-4 py-3 text-sm text-white focus:translate-y-0">Lewati ke konten</a>

    <header class="border-b border-slate-200 bg-white">
        <div class="container-page flex min-h-18 items-center justify-between gap-4 py-3">
            <a href="{{ route('home') }}" aria-label="Karhutla — Beranda" class="flex items-center gap-2.5">
                <x-icon name="leaf" class="size-7 text-forest-900" />
                <span>
                    <span class="block text-lg font-semibold">Karhutla</span>
                    <span class="hidden text-xs text-slate-500 sm:block">Pelaporan dan pemantauan</span>
                </span>
            </a>
            <nav aria-label="Navigasi utama" class="hidden items-center gap-1 lg:flex">
                @foreach(['home' => 'Beranda', 'guide' => 'Panduan', 'about' => 'Tentang aplikasi'] as $name => $label)
                    <a class="nav-link" href="{{ route($name) }}" @if(request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <a class="nav-link" href="{{ route('home') }}#pertanyaan">FAQ</a>
                <x-account-navigation class="ml-3" />
            </nav>
            <button type="button" data-menu-toggle aria-expanded="false" aria-controls="menu-mobile" aria-label="Buka menu navigasi" class="flex size-11 items-center justify-center rounded-md border border-slate-300 lg:hidden">
                <x-icon name="menu" />
            </button>
        </div>
        <nav id="menu-mobile" data-mobile-menu hidden aria-label="Navigasi seluler" class="container-page border-t border-slate-200 py-3 lg:hidden">
            <div class="flex flex-col gap-1">
                @foreach(['home' => 'Beranda', 'guide' => 'Panduan', 'about' => 'Tentang aplikasi'] as $name => $label)
                    <a class="nav-link" href="{{ route($name) }}" @if(request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <a class="nav-link" href="{{ route('home') }}#pertanyaan">FAQ</a>
                <x-account-navigation :mobile="true" class="mt-2" />
            </div>
        </nav>
    </header>

    <main id="konten" class="flex-1">
        @if(session('status'))
            <div class="container-page mt-6">
                <p role="status" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('status') }}</p>
            </div>
        @endif
        {{ $slot }}
    </main>

    <footer class="mt-8 border-t border-slate-200 bg-slate-50 py-7">
        <div class="container-page flex flex-col justify-between gap-4 text-xs leading-6 text-slate-600 sm:flex-row">
            <div>
                <p class="font-semibold text-slate-700">Karhutla · Mini Project Proyek 3</p>
                <p>Aplikasi komunitas, bukan layanan tanggap darurat.</p>
            </div>
            <nav aria-label="Navigasi footer" class="flex flex-wrap gap-x-5 gap-y-2">
                <a href="{{ route('guide') }}" class="hover:underline">Panduan pelaporan</a>
                <a href="{{ route('about') }}" class="hover:underline">Tentang aplikasi</a>
            </nav>
        </div>
    </footer>
</body>
</html>
