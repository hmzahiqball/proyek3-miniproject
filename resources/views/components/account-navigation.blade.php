@props(['mobile' => false])

<div {{ $attributes->class(['flex gap-2', 'flex-col' => $mobile, 'items-center' => ! $mobile]) }}>
    @auth
        <a href="{{ route('dashboard') }}" class="btn btn-primary" @if(request()->routeIs('dashboard', '*.dashboard')) aria-current="page" @endif>Dashboard</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline {{ $mobile ? 'w-full' : '' }}">Keluar</button>
        </form>
    @else
        <a href="{{ route('access') }}" class="btn btn-primary" @if(request()->routeIs('access')) aria-current="page" @endif>Masuk</a>
    @endauth
</div>
