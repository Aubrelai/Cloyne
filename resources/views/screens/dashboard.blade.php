@extends('layouts.app')

@section('title', 'Dashboard')
@section('search', true)
@section('topbar-actions')
    <button type="button" class="btn btn--secondary">Last 7 days</button>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <div class="page-sub">You have 12 drafts to review and 3 new replies.</div>
        </div>
        <a href="{{ route('finder') }}" class="btn btn--primary">Start a search</a>
    </div>

    <div class="stats">
        @foreach ($stats as $s)
            <x-stat :label="$s['label']" :value="$s['value']" :delta="$s['delta']" :trend="$s['trend']" :tone="! empty($s['brand']) ? 'brand' : null" />
        @endforeach
    </div>

    <div class="row">
        <section class="card grow-2">
            <div class="card__head">
                <h2 class="card__title">Lead map</h2>
                <div class="legend">
                    <span><i class="dot" style="background: var(--brand)"></i>Hot, 60+</span>
                    <span><i class="dot" style="background: var(--signal)"></i>Other leads</span>
                </div>
            </div>
            <div class="map" role="img" aria-label="Map of leads by metro area">
                @foreach ($dots as [$x, $y, $hot])
                    <span @class(['map__dot', 'map__dot--hot' => $hot]) style="left: {{ $x }}%; top: {{ $y }}%"></span>
                @endforeach
                @foreach ($labels as [$name, $x, $y])
                    <span class="map__label" style="left: {{ $x }}%; top: {{ $y }}%">{{ $name }}</span>
                @endforeach
            </div>
        </section>

        <section class="card grow-1">
            <h2 class="card__title">Lead density by metro</h2>
            @php $max = max(array_column($metros, 'count')); @endphp
            @foreach ($metros as $m)
                <div>
                    <div class="split"><span>{{ $m['name'] }}</span><span class="mono">{{ number_format($m['count']) }}</span></div>
                    <div class="bar" style="margin-top: 4px"><div class="bar__fill" style="width: {{ round($m['count'] / $max * 100) }}%"></div></div>
                </div>
            @endforeach
        </section>
    </div>

    <div class="row">
        <section class="card grow-1">
            <h2 class="card__title">Needs your attention</h2>
            <a href="{{ route('review') }}" class="list-link"><span><strong>12 drafts</strong> waiting for approval</span><span class="brand-text"><strong>Review</strong></span></a>
            <a href="{{ route('inbox') }}" class="list-link"><span><strong>3 replies</strong> the AI sorted as interested</span><span class="brand-text"><strong>Open inbox</strong></span></a>
            <a href="{{ route('booked') }}" class="list-link"><span><x-pill>Sync failed</x-pill> 1 lead didn’t reach the CRM</span><span class="brand-text"><strong>Retry</strong></span></a>
        </section>

        <section class="card grow-1">
            <h2 class="card__title">Running searches</h2>
            @forelse ($searches as $s)
                <div class="split">
                    <div><strong>{{ $s->label() }}</strong><div class="caption">Started {{ $s->created_at->diffForHumans() }}</div></div>
                    <x-pill tone="signal">Searching</x-pill>
                </div>
            @empty
                <div class="caption">No searches running.</div>
                <a href="{{ route('finder') }}" class="btn btn--secondary btn--sm" style="align-self: flex-start">Start a search</a>
            @endforelse
        </section>

        <section class="card grow-1">
            <h2 class="card__title">Booked this week</h2>
            @foreach ($bookings as $b)
                <div class="split">
                    <div><strong>{{ $b['business'] }}</strong><div class="caption">{{ $b['when'] }}</div></div>
                    <x-pill>{{ $b['synced'] ? 'In CRM' : 'Sync failed' }}</x-pill>
                </div>
            @endforeach
        </section>
    </div>
@endsection
