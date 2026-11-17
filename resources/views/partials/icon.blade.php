{{-- Line icons (24px grid, 2px stroke). Usage: @include('partials.icon', ['name' => 'folder']) --}}
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
    @case('logo')
        <path d="M4 6h16M4 12h10M4 18h6"/><circle cx="19" cy="15" r="3"/>
        @break
    @case('folder')
        <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>
        @break
    @case('plus')
        <path d="M12 5v14M5 12h14"/>
        @break
    @case('users')
        <path d="M16 20v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1"/><circle cx="9" cy="8" r="4"/><path d="M22 20v-1a4 4 0 0 0-3-3.87M16 4.13a4 4 0 0 1 0 7.75"/>
        @break
    @case('logout')
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
        @break
    @case('chevron')
        <path d="m9 18 6-6-6-6"/>
        @break
    @case('back')
        <path d="m15 18-6-6 6-6"/>
        @break
    @case('check')
        <circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>
        @break
    @case('alert')
        <circle cx="12" cy="12" r="9"/><path d="M12 8v4.5M12 16h.01"/>
        @break
    @case('eye')
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>
        @break
    @case('trash')
        <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
        @break
    @case('upload')
        <path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>
        @break
    @case('file')
        <path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8Z"/><path d="M14 3v5h5"/>
        @break
    @case('inbox')
        <path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>
        @break
    @case('key')
        <circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14.5 8.5l2 2"/>
        @break
    @case('cloud')
        <path d="M17.5 19H8a6 6 0 1 1 5.66-8h.84a4.5 4.5 0 0 1 3 8Z"/>
        @break
    @case('building')
        <path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 9h4a1 1 0 0 1 1 1v11M3 21h18M8 8h3M8 12h3M8 16h3"/>
        @break
    @case('refresh')
        <path d="M21 12a9 9 0 1 1-2.64-6.36L21 8M21 3v5h-5"/>
        @break
    @case('copy')
        <rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>
        @break
    @case('list')
        <path d="M9 6h11M9 12h11M9 18h11"/><path d="m3.5 6 1 1 2-2M3.5 12l1 1 2-2M3.5 18l1 1 2-2"/>
        @break
    @case('home')
        <rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>
        @break
    @case('bell')
        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
        @break
    @case('play')
        <path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5Z"/>
        @break
    @case('send')
        <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>
        @break
    @case('undo')
        <path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>
        @break
    @case('sun')
        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
        @break
    @case('moon')
        <path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z"/>
        @break
    @case('lock')
        <rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>
        @break
@endswitch
</svg>
