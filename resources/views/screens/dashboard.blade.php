@extends('layouts.app')

@section('title', 'Dashboard')
@section('search', true)

@section('content')
    @php
        $plus = fn ($n) => $n > 0 ? '+' . number_format($n) . ' this week' : 'None new this week';
    @endphp
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $greeting }}</h1>
            <div class="page-sub">
                {{ $today }} ·
                @if ($total === 0)
                    No leads yet. Run a search in the Lead Finder to get started.
                @else
                    {{ number_format($week['total']) }} new {{ Str::plural('lead', $week['total']) }} this week.
                @endif
            </div>
        </div>
        @can('edit')<a href="{{ route('finder') }}" class="btn btn--primary">Start a search</a>@endcan
    </div>

    @php
        $waiting = collect([
            $pendingDrafts > 0 ? ['url' => route('review'), 'text' => number_format($pendingDrafts) . ' ' . Str::plural('email', $pendingDrafts) . ' waiting for review', 'action' => 'Review'] : null,
            $draftable > 0 && $claudeConnected ? ['url' => route('review'), 'text' => number_format($draftable) . ' hot ' . Str::plural('lead', $draftable) . ' with an email and no draft yet', 'action' => 'Write'] : null,
            $notAudited > 0 ? ['url' => route('leads', ['audited' => 'no']), 'text' => number_format($notAudited) . ' ' . Str::plural('lead', $notAudited) . ' not audited yet' . ($queued > 0 ? ' (' . number_format($queued) . ' in progress)' : ''), 'action' => 'Audit'] : null,
            $failedAudits > 0 ? ['url' => route('leads', ['audited' => 'no']), 'text' => number_format($failedAudits) . ' ' . Str::plural('audit', $failedAudits) . ' failed', 'action' => 'Retry', 'tone' => 'danger'] : null,
            $failedDrafts > 0 ? ['url' => route('review', ['status' => 'failed']), 'text' => number_format($failedDrafts) . ' ' . Str::plural('email', $failedDrafts) . ' failed to write', 'action' => 'Retry', 'tone' => 'danger'] : null,
            $searches->isNotEmpty() ? ['url' => route('finder'), 'text' => $searches->count() . ' ' . Str::plural('search', $searches->count()) . ' still running', 'action' => 'Check'] : null,
            ! $apifyConnected ? ['url' => route('finder'), 'text' => 'Connect Apify to start finding leads', 'action' => 'Set up', 'tone' => 'warning'] : null,
            ! $claudeConnected ? ['url' => route('settings'), 'text' => 'Connect Claude to audit leads and write emails', 'action' => 'Set up', 'tone' => 'warning'] : null,
        ])->filter();
    @endphp

    <section class="card">
        <div class="card__head">
            <h2 class="card__title">Waiting on you</h2>
            @if ($waiting->isNotEmpty())<span class="caption">{{ $waiting->count() }} {{ Str::plural('thing', $waiting->count()) }}</span>@endif
        </div>
        @forelse ($waiting as $item)
            <a href="{{ $item['url'] }}" class="list-link">
                <span>
                    @if (! empty($item['tone']))<x-pill :tone="$item['tone']">{{ $item['tone'] === 'danger' ? 'Failed' : 'Setup' }}</x-pill>@endif
                    {{ $item['text'] }}
                </span>
                <span class="brand-text"><strong>{{ $item['action'] }}</strong></span>
            </a>
        @empty
            <div class="caption">Nothing waiting. You're all caught up.</div>
        @endforelse
    </section>

    <div class="stats">
        <x-stat label="Leads" :value="number_format($total)" :delta="$plus($week['total'])" :trend="$week['total'] > 0 ? 'up' : null" :href="route('leads')" />
        <x-stat label="With email" :value="number_format($withEmail)" :delta="$plus($week['withEmail'])" :trend="$week['withEmail'] > 0 ? 'up' : null" :href="route('leads', ['email' => 'any'])" />
        <x-stat label="Audited" :value="number_format($audited)" :delta="$plus($week['audited'])" :trend="$week['audited'] > 0 ? 'up' : null" :href="route('leads', ['audited' => 'yes'])" />
        <x-stat :label="'Hot (' . $hotScore . '+)'" :value="number_format($hot)" :delta="$plus($week['hot'])" :trend="$week['hot'] > 0 ? 'up' : null" :href="route('leads', ['score' => 'hot'])" />
        <x-stat label="Booked" :value="number_format($booked)" tone="brand" :delta="$plus($week['booked'])" :trend="$week['booked'] > 0 ? 'up' : null" :href="route('booked')" />
    </div>

    <div class="row">
        <section class="card grow-2">
            <div class="card__head">
                <h2 class="card__title">Lead map</h2>
                <div class="legend">
                    <button type="button" class="legend__toggle" data-lead-map-toggle="hot" aria-pressed="true"><i class="dot" style="background: var(--brand)"></i>Hot, {{ $hotScore }}+ <span class="mono">{{ $dots->where('hot', true)->count() }}</span></button>
                    <button type="button" class="legend__toggle" data-lead-map-toggle="other" aria-pressed="true"><i class="dot" style="background: var(--signal)"></i>Other leads <span class="mono">{{ $dots->where('hot', false)->count() }}</span></button>
                </div>
            </div>
            <div class="lead-map" data-lead-map data-lead-map-points='@json($dots)'
                 aria-label="Map of {{ $dots->count() }} leads. Click a dot to see the lead.">
                @if ($dots->isEmpty())
                    <span class="lead-map__empty">Leads appear here after your first search.</span>
                @else
                    <span class="lead-map__empty" data-lead-map-fallback>Loading the map…</span>
                @endif
            </div>
            @if ($dots->isNotEmpty())
                @push('scripts')
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" defer></script>
                    <script src="{{ asset('js/lead-map.js') }}?v=1" defer></script>
                @endpush
            @endif
        </section>

        <div class="side-col" style="display: flex; flex-direction: column; gap: 16px">
            <section class="card">
                <h2 class="card__title">Hot leads by category</h2>
                @forelse ($topCategories as $c)
                    <a href="{{ route('leads', ['category' => $c->category, 'score' => 'hot']) }}" class="split bar-link">
                        <span>{{ $c->category }}</span><span class="mono brand-text">{{ number_format($c->total) }}</span>
                    </a>
                @empty
                    <div class="caption">Hot leads show up here after audits. It hints where to search next.</div>
                @endforelse
            </section>

            <section class="card">
                <h2 class="card__title">Leads by city</h2>
                @forelse ($cities as $c)
                    <a href="{{ route('leads', ['q' => $c->city]) }}" class="bar-link">
                        <div class="split"><span>{{ $c->city }}{{ $c->state ? ', ' . $c->state : '' }}</span><span class="mono">{{ number_format($c->total) }}</span></div>
                        <div class="bar" style="margin-top: 4px"><div class="bar__fill" style="width: {{ round($c->total / $cities->first()->total * 100) }}%"></div></div>
                    </a>
                @empty
                    <div class="caption">No leads yet.</div>
                @endforelse
            </section>
        </div>
    </div>

    <section class="card">
        <h2 class="card__title">Searches</h2>
        @foreach ($searches as $s)
            <div class="split">
                <div><strong>{{ $s->label() }}</strong><div class="caption">Started <x-time :at="$s->created_at" /></div></div>
                <x-pill tone="signal">Searching</x-pill>
            </div>
        @endforeach
        @foreach ($recentSearches as $s)
            <a href="{{ route('leads', ['search' => $s->id]) }}" class="split bar-link">
                <div><strong>{{ $s->label() }}</strong><div class="caption">{{ number_format($s->added) }} new {{ Str::plural('lead', $s->added) }} · <x-time :at="$s->finished_at" /></div></div>
                <x-pill tone="success">Done</x-pill>
            </a>
        @endforeach
        @if ($searches->isEmpty() && $recentSearches->isEmpty())
            <div class="caption">No searches yet.</div>
            @can('edit')<a href="{{ route('finder') }}" class="btn btn--secondary btn--sm" style="align-self: flex-start">Start a search</a>@endcan
        @endif
    </section>
@endsection
