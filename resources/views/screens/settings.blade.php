@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <h1 class="page-title">Settings</h1>

    <div class="row">
        <section class="card" style="flex: 1 1 420px">
            <div class="card__head"><h2 class="card__title">Sending inboxes</h2><button type="button" class="btn btn--secondary btn--sm">Add inbox</button></div>
            <div class="caption">Cold email only sends from these separate domains, never from LaiQ's main domain.</div>
            @foreach ($inboxes as $i)
                <div class="split">
                    <span class="mono">{{ $i['address'] }}</span>
                    <span style="display: flex; gap: 16px; align-items: center"><span class="caption">{{ $i['cap'] }}</span><x-pill>{{ $i['health'] }}</x-pill></span>
                </div>
            @endforeach
            <div class="split" style="padding-top: 8px; border-top: 1px solid var(--border)"><span>Sending service</span><x-pill tone="success">Instantly connected</x-pill></div>
        </section>

        <form class="card" style="flex: 1 1 420px" method="post" action="#">
            @csrf
            <h2 class="card__title">CRM connection</h2>
            <div style="display: flex; align-items: center; gap: 8px"><x-pill>Connected</x-pill><span class="caption">LaiQ Digital CRM · last sync 4 min ago</span></div>
            <div class="field"><label for="crm-url" class="label">CRM API address</label><input id="crm-url" name="crm_url" type="url" class="input input--mono" placeholder="https://your-crm.com/api/leads"></div>
            <div class="field"><label for="crm-key" class="label">Secret key</label><input id="crm-key" name="crm_key" type="password" class="input input--mono" autocomplete="off"></div>
            <div class="btn-row"><button type="button" class="btn btn--secondary">Test connection</button><button type="submit" class="btn btn--primary">Save</button></div>
        </form>

        <section class="card" style="flex: 1 1 420px">
            <h2 class="card__title">Scoring rules</h2>
            <div class="caption">Points per finding. Leads scoring 60+ are drafted for outreach.</div>
            <div style="display: grid; grid-template-columns: minmax(0, 1fr) 72px; gap: 8px 16px; align-items: center">
                @foreach ($scoring as $i => [$label, $points])
                    <label for="w{{ $i }}">{{ $label }}</label>
                    <input id="w{{ $i }}" name="weights[{{ $i }}]" type="number" class="input input--mono" value="{{ $points }}">
                @endforeach
            </div>
        </section>

        <section class="card" style="flex: 1 1 420px">
            <h2 class="card__title">Fact sheet for the reply agent</h2>
            <div class="caption">The AI only answers questions using what's written here. It never invents prices, results or client names.</div>
            <label for="facts" class="sr-only">Fact sheet</label>
            <textarea id="facts" name="facts" rows="8" class="textarea">Services: Meta ads, CRM setup with automatic follow-up, websites.
Website: $500 setup.
Meta ads + CRM: $750 CRM setup, then $1,500–2,000/month. Ad spend is paid by the client.
Booking link: [BOOKING LINK]
Hand off to a person for: discounts, contracts, complaints.</textarea>
        </section>

        <section class="card" style="flex: 1 1 100%">
            <div class="card__head"><h2 class="card__title">Team</h2><button type="button" class="btn btn--secondary btn--sm">Invite</button></div>
            <div style="display: grid; grid-template-columns: minmax(0, 1fr) 160px; gap: 8px 16px; align-items: center">
                <div><strong>Aubre' Lai Cyriaque</strong><div class="caption">Owner account</div></div><span class="muted">Owner</span>
                <div><strong>[TEAM MEMBER]</strong><div class="caption">[EMAIL]</div></div>
                <label><span class="sr-only">Role</span><select class="select" style="width: 100%"><option>Reviewer</option><option>Viewer</option></select></label>
            </div>
        </section>
    </div>
@endsection
