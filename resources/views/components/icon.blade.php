@props(['name' => 'arrow'])
<svg {{ $attributes->class(['size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('leaf') <path d="M20 4c-8-2-16 1-16 8a7 7 0 0 0 7 7c7 0 9-7 9-15Z"/><path d="m4 21 11-11M9 16v-5m0 5h5"/> @break
        @case('pin') <path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/> @break
        @case('shield') <path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/> @break
        @case('file') <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/> @break
        @case('users') <circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m3 10v-3a6 6 0 0 0-2-4"/> @break
        @case('check') <path d="m5 12 4 4L19 6"/> @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16"/> @break
        @case('eye') <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('info') <circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.1"/> @break
        @case('camera') <path d="M3 7h4l2-3h6l2 3h4v13H3Z"/><circle cx="12" cy="13" r="4"/> @break
        @default <path d="M4 12h16m-6-6 6 6-6 6"/>
    @endswitch
</svg>
