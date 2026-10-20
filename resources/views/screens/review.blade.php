@extends('layouts.app')

@section('title', 'Review queue')
@section('search', true)

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Review queue</h1>
            <div class="page-sub">12 AI drafts waiting · nothing sends until you approve it</div>
        </div>
        <button type="button" class="btn btn--secondary">Approve all scoring 80+ (5)</button>
    </div>

    <div class="row">
        <section class="card card--flush side-col" aria-label="Drafts">
            <div class="tabs">
                <button type="button" class="btn btn--secondary btn--sm" aria-pressed="true">Emails (12)</button>
                <button type="button" class="btn btn--ghost btn--sm" aria-pressed="false">Replies (3)</button>
            </div>
            @foreach ($drafts as $d)
                <a href="#" @class(['list-item', 'is-selected' => $loop->first])>
                    <div class="split"><strong>{{ $d['business'] }}</strong><x-score :value="$d['score']" /></div>
                    <div class="caption" style="margin-top: 2px">{{ $d['subject'] }}</div>
                </a>
            @endforeach
        </section>

        <article class="card card--flush grow-main" style="max-width: 760px">
            <header class="email__head">
                <div>
                    <strong>Mike Torres · Sunshine Roofing Co.</strong>
                    <div class="caption">mike@sunshineroofingfl.com · Step 1 of 3 · from inbox alex@getlaiq.com</div>
                </div>
                <div style="display: flex; gap: 8px; align-items: center"><x-pill>AI drafted</x-pill><a href="{{ route('leads') }}" style="font-size: 13px; font-weight: 600">View lead</a></div>
            </header>

            <div class="finding" style="margin: 16px 16px 0">Built on: running 3 Meta ads, but no lead form or booking on the site</div>

            <div class="field" style="padding: 16px 16px 0">
                <label for="subject" class="label">Subject</label>
                <input id="subject" type="text" class="input" style="font-weight: 600" value="Quick question about your Meta ads">
            </div>

            <div class="email__body">
                <p>Hi Mike,</p>
                <p><mark>Saw you’re running Meta ads for Sunshine Roofing, but the page they land on has no way to book an inspection.</mark> That usually means paying for clicks that never turn into calls.</p>
                <p>We set up booking flows that send those leads straight to your calendar, so every click has somewhere to go.</p>
                <p>Worth a 10-minute call this week?</p>
                <p>Alex<br>LaiQ Digital</p>
                <p class="caption">[LAIQ MAILING ADDRESS] · Not interested? Reply “stop” and we won’t email again.</p>
            </div>

            <footer class="email__foot">
                <span class="caption">Sends Tue 9:14 AM · follow-ups in 3 and 7 days</span>
                <div class="btn-row">
                    <button type="button" class="btn btn--ghost">Reject</button>
                    <button type="button" class="btn btn--secondary">Rewrite with AI</button>
                    <button type="button" class="btn btn--primary">Approve and send</button>
                </div>
            </footer>
        </article>
    </div>
@endsection
