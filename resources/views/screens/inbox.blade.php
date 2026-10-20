@extends('layouts.app')

@section('title', 'Inbox')

@section('content')
    <div>
        <h1 class="page-title">Inbox</h1>
        <div class="page-sub">The AI sorts every reply and drafts an answer. You approve it, edit it, or take over.</div>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 8px">
        @foreach ($filters as [$name, $count, $on])
            <button type="button" @class(['chip', 'is-on' => $on]) aria-pressed="{{ $on ? 'true' : 'false' }}">{{ $name }} · {{ $count }}</button>
        @endforeach
    </div>

    <div class="row">
        <section class="card card--flush side-col" aria-label="Replies">
            @foreach ($replies as $r)
                <a href="#" @class(['list-item', 'is-selected' => $loop->first])>
                    <div class="split"><strong>{{ $r['name'] }}</strong><span class="caption">{{ $r['when'] }}</span></div>
                    <div class="caption">{{ $r['business'] }}</div>
                    <div style="font-size: 13px; margin-top: 4px">{{ $r['preview'] }}</div>
                </a>
            @endforeach
        </section>

        <article class="card card--flush grow-main" style="max-width: 760px">
            <header class="email__head">
                <div>
                    <strong>Mike Torres · Sunshine Roofing Co.</strong>
                    <div class="caption">Roofers · Florida · reply to email 1 · turn 1 of 3 before a human steps in</div>
                </div>
                <div style="display: flex; gap: 8px; align-items: center"><x-pill>Interested</x-pill><a href="{{ route('leads') }}" style="font-size: 13px; font-weight: 600">View lead</a></div>
            </header>

            <div class="thread">
                <div class="bubble bubble--out">
                    <div class="caption" style="margin-bottom: 4px">You · Tue 9:14 AM</div>
                    Saw you're running Meta ads for Sunshine Roofing, but the page they land on has no way to book an inspection. Worth a 10-minute call this week?
                </div>
                <div class="bubble bubble--in">
                    <div class="caption" style="margin-bottom: 4px">Mike · Tue 2:41 PM</div>
                    Yeah, we've been wondering why the ads get clicks but the phone doesn't ring. What would this cost us?
                </div>
            </div>

            <div class="suggest">
                <div class="split" style="padding: 10px 12px; border-bottom: 1px solid var(--border)">
                    <x-pill>AI suggested reply</x-pill>
                    <span class="caption">Uses the fact sheet · pricing range only</span>
                </div>
                <div class="email__body" style="padding: 12px">
                    <p>Great question, Mike. It depends on how much you're running, but most roofing clients land in the [PRICE RANGE] per month range, and your ad spend stays yours.</p>
                    <p>Easiest is a quick call so I can see your current setup. Here are a few times: <a href="#">[BOOKING LINK]</a></p>
                </div>
                <div class="btn-row" style="padding: 12px; border-top: 1px solid var(--border)">
                    <button type="button" class="btn btn--ghost">Take over</button>
                    <button type="button" class="btn btn--secondary">Edit</button>
                    <button type="button" class="btn btn--primary">Send reply</button>
                </div>
            </div>
        </article>
    </div>
@endsection
