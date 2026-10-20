@php
    $audit = $lead->latestAudit;
    $contact = $lead->primaryContact;
    $tier = is_null($lead->score) ? null : ($lead->score >= 60 ? 'hot' : ($lead->score >= 40 ? 'warm' : 'cold'));
@endphp
<aside class="panel" aria-label="Lead profile">
    <div class="panel__head">
        <div>
            <h2 style="font: 600 20px/28px var(--font-display)">{{ $lead->name }}</h2>
            <div class="caption" style="margin-top: 2px">
                {{ collect([$lead->category, $lead->location(), $audit ? 'Audited ' . $audit->audited_at->diffForHumans() : 'Not audited yet'])->filter()->implode(' · ') }}
            </div>
        </div>
        <a href="{{ request()->fullUrlWithQuery(['lead' => null]) }}" class="btn btn--ghost btn--sm" aria-label="Close lead profile"><x-icon name="close" width="16" height="16" /></a>
    </div>

    <div class="panel__section" style="display: flex; align-items: center; gap: 12px">
        @if ($tier)
            <span @class(['brand-text' => $tier === 'hot']) style="font: 600 40px/44px var(--font-display); {{ $tier === 'warm' ? 'color: var(--warning)' : '' }} {{ $tier === 'cold' ? 'color: var(--ink-muted)' : '' }}">{{ $lead->score }}</span>
        @endif
        <div style="display: flex; flex-direction: column; gap: 4px">
            <span class="label">{{ $tier ? 'Fit score · ' . $tier : 'No score yet' }}</span>
            <span style="display: flex; flex-wrap: wrap; gap: 6px">
                <x-pill>{{ $contact?->emailLabel() ?? 'Not found' }}</x-pill>
                @if ($lead->license_status)
                    <x-pill :tone="$lead->license_status === 'Active' ? 'success' : 'warning'">License {{ strtolower($lead->license_status) }}</x-pill>
                @endif
                <x-pill tone="neutral">{{ $lead->stageLabel() }}</x-pill>
            </span>
        </div>
    </div>

    <div class="panel__section">
        <dl class="facts">
            <dt>Contact</dt><dd>{{ $contact?->name ? $contact->name . ($contact->role ? ' · ' . $contact->role : '') : '—' }}</dd>
            <dt>Email</dt><dd class="mono">{{ $contact?->email ?? '—' }}</dd>
            <dt>Phone</dt><dd class="mono">{{ $lead->phone ?? '—' }}</dd>
            <dt>Website</dt><dd>@if ($lead->website)<a href="{{ $lead->website }}" target="_blank" rel="noopener">{{ parse_url($lead->website, PHP_URL_HOST) }}</a>@else — @endif</dd>
            <dt>License</dt><dd class="mono">{{ $lead->license_number ?? '—' }}{{ $lead->license_expires_on ? ' · exp. ' . $lead->license_expires_on->format('M Y') : '' }}</dd>
            <dt>Reviews</dt><dd>{{ $lead->rating ? $lead->rating . ' stars · ' . $lead->review_count . ' on Google' : '—' }}</dd>
        </dl>
    </div>

    @if ($lead->package)
        @php
            $all = \App\Models\Business::packages();
            $best = $all[$lead->package] ?? null;
            $pitchKey = $lead->pitchPackage();
            $pitch = $pitchKey ? ($all[$pitchKey] ?? null) : null;
        @endphp
        <div class="panel__section" style="display: flex; flex-direction: column; gap: 10px">
            <div>
                <div class="label">Best fit</div>
                <div class="split" style="margin-top: 4px"><strong>{{ $best['name'] ?? $lead->package }}</strong><span class="caption">{{ $best['price'] ?? '' }}</span></div>
                @if ($best)
                    <div class="caption" style="margin-top: 2px">{{ $best['fits'] }}</div>
                @endif
            </div>
            <div>
                <div class="label">Pitch now</div>
                @if ($pitch)
                    <div class="split" style="margin-top: 4px"><strong class="brand-text">{{ $pitch['name'] }}</strong><span class="caption">{{ $pitch['price'] }}</span></div>
                    @if ($pitchKey !== $lead->package)
                        <div class="caption" style="margin-top: 2px">{{ $best['short'] ?? 'Their best fit' }} isn’t on sale yet. Start with {{ $pitch['short'] }}, then upgrade.</div>
                    @endif
                @else
                    <div class="caption" style="margin-top: 4px">Nothing to pitch yet. Hold until {{ $best['short'] ?? 'their best fit' }} is on sale.</div>
                @endif
            </div>
        </div>
    @endif

    @if ($audit)
        <div class="card__title" style="padding: 12px 16px 4px">Why it scored {{ $audit->score }}</div>
        <ul class="audit">
            @foreach ($audit->checks as $check)
                <li class="audit__item">
                    <span @class(['audit__icon', 'audit__icon--yes' => $check['passed'], 'audit__icon--no' => ! $check['passed']])>{{ $check['passed'] ? '✓' : '✗' }}</span>
                    <div><strong>{{ $check['title'] }}</strong><div class="caption">{{ $check['detail'] }}</div></div>
                    <span @class(['audit__points', 'audit__points--zero' => $check['points'] === 0])>{{ $check['points'] > 0 ? '+' : '' }}{{ $check['points'] }}</span>
                </li>
            @endforeach
        </ul>
        @if ($audit->pitch)
            <div class="finding" style="margin: 0 16px">Best pitch: {{ $audit->pitch }}</div>
        @endif
    @else
        <div class="panel__section caption">This business hasn't been audited yet.</div>
    @endif

    <div class="btn-row" style="padding: 16px">
        <button type="button" class="btn btn--ghost" disabled>Re-run audit</button>
        <button type="button" class="btn btn--secondary" disabled>Send to CRM</button>
        <a href="{{ route('review') }}" class="btn btn--primary">Review draft</a>
    </div>
</aside>
