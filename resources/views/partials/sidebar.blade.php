@php
    $items = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['route' => 'finder', 'label' => 'Lead Finder', 'icon' => 'search'],
        ['route' => 'leads', 'label' => 'Leads', 'icon' => 'list'],
        ['route' => 'review', 'label' => 'Review queue', 'icon' => 'review', 'count' => $pendingDrafts ?? 0],
        ['route' => 'campaigns', 'label' => 'Campaigns', 'icon' => 'send'],
        ['route' => 'inbox', 'label' => 'Inbox', 'icon' => 'inbox'],
        ['route' => 'booked', 'label' => 'Booked', 'icon' => 'calendar'],
    ];
@endphp
<nav class="nav" aria-label="Main">
    @php $latest = collect(config('versions', []))->sort(fn ($a, $b) => version_compare($b['version'], $a['version']))->first(); @endphp
    <div class="nav__brand-wrap">
        <a href="{{ route('dashboard') }}" class="nav__brand">Cloyne</a>
        @if ($latest)
            <a href="{{ route('versions') }}" class="nav__version" title="What's new in {{ $latest['version'] }}: {{ $latest['title'] }}">v{{ $latest['version'] }} · {{ \Illuminate\Support\Carbon::parse($latest['date'])->format('M j') }}</a>
        @endif
    </div>

    @foreach ($items as $item)
        @php $active = request()->routeIs($item['route']); @endphp
        <a href="{{ route($item['route']) }}" @class(['nav__item', 'is-active' => $active]) @if ($active) aria-current="page" @endif>
            <x-icon :name="$item['icon']" />
            {{ $item['label'] }}
            @if (! empty($item['count']))
                <span class="nav__count">{{ $item['count'] }}</span>
            @endif
        </a>
    @endforeach

    <div class="nav__spacer"></div>

    @php $active = request()->routeIs('settings'); @endphp
    <a href="{{ route('settings') }}" @class(['nav__item', 'is-active' => $active]) @if ($active) aria-current="page" @endif>
        <x-icon name="settings" />
        Settings
    </a>
</nav>
