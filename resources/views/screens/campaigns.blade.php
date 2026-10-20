@extends('layouts.app')

@section('title', 'Campaigns')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Campaigns</h1>
            <div class="page-sub">Each campaign is a 3-email sequence sent from warmed inboxes. Any reply stops it.</div>
        </div>
        <button type="button" class="btn btn--primary">New campaign</button>
    </div>

    <div class="grid-3">
        @foreach ($campaigns as $c)
            <a href="#" @class(['campaign-card', 'is-selected' => $loop->first])>
                <div class="split"><strong>{{ $c['name'] }}</strong><x-pill :tone="$c['status'] === 'Active' ? 'success' : 'neutral'">{{ $c['status'] }}</x-pill></div>
                <div class="mini-stats">
                    <div><strong>{{ $c['sent'] }}</strong><span class="caption">sent</span></div>
                    <div><strong>{{ $c['replies'] }}</strong><span class="caption">replies</span></div>
                    <div><strong @class(['brand-text' => $c['booked'] > 0])>{{ $c['booked'] }}</strong><span class="caption">booked</span></div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="row">
        <section class="card" style="flex: 3 1 480px">
            <div class="card__head">
                <h2 class="card__title">Roofers · Florida — sequence</h2>
                <div class="btn-row"><button type="button" class="btn btn--secondary btn--sm">Edit steps</button><button type="button" class="btn btn--ghost btn--sm">Pause</button></div>
            </div>
            <ol class="steps">
                @foreach ($steps as $step)
                    <li class="step">
                        <span class="mono muted">{{ $step['day'] }}</span>
                        <div><strong>{{ $step['title'] }}</strong><div class="caption">{{ $step['detail'] }}</div></div>
                        <span class="mono">{{ $step['stats'] }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="card" style="flex: 2 1 340px">
            <h2 class="card__title">Sending inboxes</h2>
            @foreach ($inboxes as $i)
                <div class="split" style="padding: 8px 0; @if (! $loop->last) border-bottom: 1px solid var(--border); @endif">
                    <div><div class="mono">{{ $i['address'] }}</div><div class="caption">{{ $i['usage'] }}</div></div>
                    <x-pill>{{ $i['health'] }}</x-pill>
                </div>
            @endforeach
        </section>
    </div>
@endsection
