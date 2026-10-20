@props(['name'])
{{-- Line icons, 1.5px stroke. They take the text color they sit beside. --}}
<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" {{ $attributes->merge(['width' => 20, 'height' => 20]) }}>
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>
            @break
        @case('list')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('review')
            <path d="M4 5h16v11H8l-4 4z"/><path d="M8 10l2.5 2.5L16 8"/>
            @break
        @case('send')
            <path d="M3 12l18-8-7 18-3-7z"/>
            @break
        @case('inbox')
            <path d="M3 13l3-8h12l3 8v6H3z"/><path d="M3 13h5l1 2h6l1-2h5"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
            @break
        @case('close')
            <path d="M6 6l12 12M18 6L6 18"/>
            @break
    @endswitch
</svg>
