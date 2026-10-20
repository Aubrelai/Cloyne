@extends('layouts.app')

@section('title', 'Dashboard')
@section('search', true)

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <div class="page-sub">
                @if ($total === 0)
                    No leads yet. Run a search in the Lead Finder to get started.
                @else
                    {{ number_format($newThisWeek) }} new {{ Str::plural('lead', $newThisWeek) }} this week.
                @endif
            </div>
        </div>
        <a href="{{ route('finder') }}" class="btn btn--primary">Start a search</a>
    </div>

    <div class="stats">
        <x-stat label="Leads" :value="number_format($total)" />
        <x-stat label="With email" :value="number_format($withEmail)" />
        <x-stat label="Audited" :value="number_format($audited)" />
        <x-stat label="Hot (60+)" :value="number_format($hot)" />
        <x-stat label="Booked" :value="number_format($booked)" tone="brand" />
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
            <div class="map" role="img" aria-label="Map of {{ $dots->count() }} leads across the US">
                @foreach ($dots as $d)
                    <span @class(['map__dot', 'map__dot--hot' => $d['hot']]) style="left: {{ $d['x'] }}%; top: {{ $d['y'] }}%"></span>
                @endforeach
                @if ($dots->isEmpty())
                    <span class="map__label" style="left: 50%; top: 50%; transform: translate(-50%, -50%)">Leads appear here after your first search.</span>
                @endif
            </div>
        </section>

        <section class="card grow-1">
            <h2 class="card__title">Leads by city</h2>
            @forelse ($cities as $c)
                <div>
                    <div class="split"><span>{{ $c->city }}{{ $c->state ? ', ' . $c->state : '' }}</span><span class="mono">{{ number_format($c->total) }}</span></div>
                    <div class="bar" style="margin-top: 4px"><div class="bar__fill" style="width: {{ round($c->total / $cities->first()->total * 100) }}%"></div></div>
                </div>
            @empty
                <div class="caption">No leads yet.</div>
            @endforelse
        </section>
    </div>

    <div class="row">
        <section class="card grow-1">
            <h2 class="card__title">Needs your attention</h2>
            @if (! $apifyConnected)
                <a href="{{ route('finder') }}" class="list-link"><span><x-pill tone="warning">Setup</x-pill> Connect Apify to start finding leads</span><span class="brand-text"><strong>Set up</strong></span></a>
            @endif
            @if ($notAudited > 0)
                <a href="{{ route('leads') }}" class="list-link"><span><strong>{{ number_format($notAudited) }} {{ Str::plural('lead', $notAudited) }}</strong> {{ $queued > 0 ? 'not audited yet (' . number_format($queued) . ' in progress)' : 'not audited yet' }}</span><span class="brand-text"><strong>Audit</strong></span></a>
            @endif
            @if ($hot > 0)
                <a href="{{ route('leads', ['score' => 'hot']) }}" class="list-link"><span><strong>{{ number_format($hot) }} hot {{ Str::plural('lead', $hot) }}</strong> ready to contact</span><span class="brand-text"><strong>View</strong></span></a>
            @endif
            @if ($apifyConnected && $notAudited === 0 && $hot === 0)
                <div class="caption">Nothing right now.</div>
            @endif
        </section>

        <section class="card grow-1">
            <h2 class="card__title">Searches</h2>
            @foreach ($searches as $s)
                <div class="split">
                    <div><strong>{{ $s->label() }}</strong><div class="caption">Started {{ $s->created_at->diffForHumans() }}</div></div>
                    <x-pill tone="signal">Searching</x-pill>
                </div>
            @endforeach
            @foreach ($recentSearches as $s)
                <div class="split">
                    <div><strong>{{ $s->label() }}</strong><div class="caption">{{ number_format($s->added) }} new {{ Str::plural('lead', $s->added) }} · {{ $s->finished_at?->diffForHumans() }}</div></div>
                    <x-pill tone="success">Done</x-pill>
                </div>
            @endforeach
            @if ($searches->isEmpty() && $recentSearches->isEmpty())
                <div class="caption">No searches yet.</div>
                <a href="{{ route('finder') }}" class="btn btn--secondary btn--sm" style="align-self: flex-start">Start a search</a>
            @endif
        </section>
    </div>
@endsection
