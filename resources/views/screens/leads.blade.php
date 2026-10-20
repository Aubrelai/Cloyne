@extends('layouts.app')

@section('title', 'Leads')
@section('search', true)

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Leads</h1>
            <div class="page-sub">20,027 businesses · 1,236 scored 60+</div>
        </div>
        <div class="btn-row">
            <button type="button" class="btn btn--secondary">Export</button>
            <button type="button" class="btn btn--primary">Add 3 to campaign</button>
        </div>
    </div>

    <div class="filters">
        <div class="field"><label for="f-trade" class="label">Trade</label><select id="f-trade" class="select"><option>All trades</option><option>Roofing</option><option>Plumbing</option><option>HVAC</option></select></div>
        <div class="field"><label for="f-area" class="label">Area</label><select id="f-area" class="select"><option>All areas</option><option>Florida</option><option>Texas</option></select></div>
        <div class="field"><label for="f-score" class="label">Score</label><select id="f-score" class="select"><option>Any score</option><option>60+ (hot)</option><option>40–59 (warm)</option></select></div>
        <div class="field"><label for="f-email" class="label">Email</label><select id="f-email" class="select"><option>Any status</option><option>Verified</option><option>Risky</option></select></div>
    </div>

    <div class="row">
        <div class="card card--flush grow-main table-wrap">
            <table class="table">
                <thead><tr><th>Business</th><th>Trade</th><th>Score</th><th>Email</th><th>Stage</th></tr></thead>
                <tbody>
                @foreach ($leads as $lead)
                    <tr @class(['is-selected' => $loop->first])>
                        <td><div class="table__name">{{ $lead['name'] }}</div><div class="caption">{{ $lead['city'] }}</div></td>
                        <td>{{ $lead['trade'] }}</td>
                        <td><x-score :value="$lead['score']" /></td>
                        <td><x-pill>{{ $lead['email'] }}</x-pill></td>
                        <td>{{ $lead['stage'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <aside class="panel" aria-label="Lead profile">
            <div class="panel__head">
                <div>
                    <h2 style="font: 600 20px/28px var(--font-display)">Sunshine Roofing Co.</h2>
                    <div class="caption" style="margin-top: 2px">Roofing · Wellington, FL · Audited 2h ago</div>
                </div>
                <button type="button" class="btn btn--ghost btn--sm" aria-label="Close lead profile"><x-icon name="close" width="16" height="16" /></button>
            </div>

            <div class="panel__section" style="display: flex; align-items: center; gap: 12px">
                <span class="brand-text" style="font: 600 40px/44px var(--font-display)">92</span>
                <div style="display: flex; flex-direction: column; gap: 4px">
                    <span class="label">Fit score · hot</span>
                    <span style="display: flex; gap: 6px"><x-pill tone="success">Verified email</x-pill><x-pill tone="success">License active</x-pill></span>
                </div>
            </div>

            <div class="panel__section">
                <dl class="facts">
                    <dt>Owner</dt><dd>Mike Torres</dd>
                    <dt>Email</dt><dd class="mono">mike@sunshineroofingfl.com</dd>
                    <dt>Phone</dt><dd class="mono">(561) 555-0142</dd>
                    <dt>License</dt><dd class="mono">[LICENSE #] · exp. 2027</dd>
                    <dt>Reviews</dt><dd>4.8 stars · 126 on Google</dd>
                </dl>
            </div>

            <div class="card__title" style="padding: 12px 16px 4px">Why it scored 92</div>
            <ul class="audit">
                @foreach ($audit as $check)
                    <li class="audit__item">
                        <span @class(['audit__icon', 'audit__icon--yes' => $check['yes'], 'audit__icon--no' => ! $check['yes']])>{{ $check['yes'] ? '✓' : '✗' }}</span>
                        <div><strong>{{ $check['title'] }}</strong><div class="caption">{{ $check['detail'] }}</div></div>
                        <span @class(['audit__points', 'audit__points--zero' => $check['points'] === 0])>+{{ $check['points'] }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="finding" style="margin: 0 16px">Best pitch: paying for Meta clicks that land on a page with no way to book.</div>

            <div class="btn-row" style="padding: 16px">
                <button type="button" class="btn btn--ghost">Re-run audit</button>
                <button type="button" class="btn btn--secondary">Send to CRM</button>
                <a href="{{ route('review') }}" class="btn btn--primary">Review draft</a>
            </div>
        </aside>
    </div>
@endsection
