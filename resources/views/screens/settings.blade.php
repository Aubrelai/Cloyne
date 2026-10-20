@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <h1 class="page-title">Settings</h1>

    <section class="card">
        <h2 class="card__title">Connections</h2>
        <div class="caption">Keys live in the <span class="mono">.env</span> file in your Cloyne folder, never in this app or on GitHub.</div>

        <div class="split" style="padding: 8px 0; border-bottom: 1px solid var(--border)">
            <div><strong>Apify</strong><div class="caption">Lead Finder: Google Maps searches · <span class="mono">APIFY_TOKEN</span></div></div>
            @if ($apifyConnected)
                <x-pill tone="success">Connected</x-pill>
            @else
                <x-pill tone="warning">Not connected</x-pill>
            @endif
        </div>
        <div class="split" style="padding: 8px 0; border-bottom: 1px solid var(--border)">
            <div><strong>Claude API</strong><div class="caption">AI audit, scoring and email drafts</div></div>
            <x-pill tone="neutral">Step 3</x-pill>
        </div>
        <div class="split" style="padding: 8px 0; border-bottom: 1px solid var(--border)">
            <div><strong>Instantly</strong><div class="caption">Sending and inbox warm-up</div></div>
            <x-pill tone="neutral">Step 5</x-pill>
        </div>
        <div class="split" style="padding: 8px 0">
            <div><strong>LaiQ Digital CRM</strong><div class="caption">Booked calls go here; deal stages come back</div></div>
            <x-pill tone="neutral">Step 7</x-pill>
        </div>
    </section>

    <section class="card">
        <div class="card__head">
            <h2 class="card__title">LaiQ packages</h2>
            <span class="caption">Edit in <span class="mono">config/cloyne.php</span> · on sale: <span class="mono">CLOYNE_SELLING</span> in <span class="mono">.env</span></span>
        </div>
        <div class="grid-3">
            @foreach (\App\Models\Business::packages() as $key => $pkg)
                <div class="campaign-card">
                    <div class="split">
                        <strong>{{ $pkg['name'] }}</strong>
                        @if (is_null($pkg['order']))
                            <x-pill tone="neutral">Quote only</x-pill>
                        @elseif (in_array($key, \App\Models\Business::sellingNow(), true))
                            <x-pill tone="success">On sale</x-pill>
                        @else
                            <x-pill tone="warning">Paused</x-pill>
                        @endif
                    </div>
                    <div class="caption">{{ $pkg['price'] }}</div>
                    <div style="font-size: 13px">{{ $pkg['fits'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2 class="card__title">Team</h2>
        <div class="caption">Logins and roles (Owner, Reviewer, Viewer) are added before Cloyne goes online. Right now Cloyne only runs on your computer.</div>
    </section>
@endsection
