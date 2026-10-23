@php
    $audit = $lead->latestAudit;
    $contact = $lead->primaryContact;
    $hotScore = (int) config('cloyne.outreach.min_score', 60);
    $tier = is_null($lead->score) ? null : ($lead->score >= $hotScore ? 'hot' : ($lead->score >= 40 ? 'warm' : 'cold'));
    $auditAge = $lead->auditAgeDays();
    $stale = $auditAge !== null && $auditAge >= 90;
    $signals = $audit?->signals ?? [];
@endphp
<aside class="panel" aria-label="Lead profile">
    <div class="panel__head">
        <div>
            <h2 style="font: 600 20px/28px var(--font-display)">{{ $lead->displayName() }}</h2>
            @if ($lead->displayName() !== $lead->name)
                <div class="caption" title="Name on Google">On Google: {{ $lead->name }}</div>
            @endif
            <div class="caption" style="margin-top: 2px">
                {{ collect([$lead->category, $lead->location()])->filter()->implode(' · ') }} ·
                @if ($audit)
                    <span @class(['stale' => $stale])>Audited <x-time :at="$audit->audited_at" /></span>
                @else
                    Not audited yet
                @endif
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
                @if ($lead->is_chain)<x-pill tone="warning">Chain</x-pill>@endif
                @if ($stale)<x-pill tone="warning">Audit {{ $auditAge }} days old</x-pill>@endif
            </span>
        </div>
    </div>

    @if ($lead->stage === 'excluded')
        <div class="panel__section">
            <div class="split" style="flex-wrap: wrap">
                <span><x-pill tone="warning">Excluded</x-pill> {{ $lead->exclude_reason ?: 'No reason given' }}</span>
                @can('edit')
                    <form method="post" action="{{ route('leads.include', $lead) }}">
                        @csrf
                        <button type="submit" class="btn btn--secondary btn--sm">Bring back</button>
                    </form>
                @endcan
            </div>
        </div>
    @endif

    <div class="panel__section">
        <dl class="facts">
            <dt>Contact</dt><dd>{{ $contact?->name ? $contact->name . ($contact->role ? ' · ' . $contact->role : '') : '—' }}</dd>
            <dt>Email</dt><dd>@if ($contact?->email)<span class="with-copy"><a href="mailto:{{ $contact->email }}" class="mono tel">{{ $contact->email }}</a><x-copy :value="$contact->email" label="email" /></span>@else — @endif
                @foreach ($lead->contacts->whereNotNull('email')->where('id', '!=', $contact?->id)->take(3) as $other)
                    <div class="with-copy caption"><span class="mono">{{ $other->email }}</span><x-copy :value="$other->email" label="email" /></div>
                @endforeach
            </dd>
            <dt>Phone</dt><dd><x-phone :number="$lead->phone" /></dd>
            <dt>Website</dt><dd>@if ($lead->website)<span class="with-copy"><a href="{{ $lead->website }}" target="_blank" rel="noopener">{{ $lead->website_host ?: parse_url($lead->website, PHP_URL_HOST) }}</a><x-copy :value="$lead->website" label="website" /></span>@else — @endif</dd>
            <dt>Google</dt><dd>@if ($lead->mapsUrl())<a href="{{ $lead->mapsUrl() }}" target="_blank" rel="noopener">Open in Google Maps</a>@else — @endif</dd>
            <dt>License</dt><dd class="mono">{{ collect([$lead->license_number, $lead->license_status, $lead->license_expires_on ? 'exp. ' . $lead->license_expires_on->format('M Y') : null])->filter()->implode(' · ') ?: '—' }}</dd>
            <dt>Reviews</dt><dd>{{ $lead->rating ? $lead->rating . ' stars · ' . number_format($lead->review_count) . ' on Google' : '—' }}</dd>
            <dt>Added</dt><dd><x-time :at="$lead->created_at" />{{ $lead->search ? ' · ' . $lead->search->label() : '' }}</dd>
            @if ($lead->crm_id)
                <dt>CRM</dt><dd>On the call list{{ $lead->crm_list ? ' "' . $lead->crm_list . '"' : '' }} · <span class="mono">#{{ $lead->crm_id }}</span><div class="caption">The team calls this lead from the CRM, so Cloyne won't email it.</div></dd>
            @endif
        </dl>

        @php $calls = $lead->calls()->limit(10)->get(); @endphp
        @if ($calls->isNotEmpty())
            <h3 class="profile__subhead">Calls <span class="caption">from the CRM</span></h3>
            <ul class="call-list">
                @foreach ($calls as $call)
                    <li>
                        <div class="split">
                            <x-pill :tone="match ($call->outcome) { 'booked' => 'success', 'not_interested', 'wrong_number' => 'warning', default => 'neutral' }">{{ $call->outcomeLabel() }}</x-pill>
                            <span class="caption"><x-time :at="$call->called_at" />{{ $call->caller ? ' · ' . $call->caller : '' }}</span>
                        </div>
                        @if ($call->call_back_on)<div class="caption">Call back {{ $call->call_back_on->format('M j') }}</div>@endif
                        @if ($call->notes)<div class="call-list__notes">{{ $call->notes }}</div>@endif
                    </li>
                @endforeach
            </ul>
        @endif
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
        @if ($signals)
            @php
                $yes = fn ($v) => $v ? 'Yes' : 'No';
                $scan = array_filter([
                    'Site loaded' => isset($signals['reachable']) ? $yes($signals['reachable']) : null,
                    'Built with' => $signals['built_with'] ?? null,
                    'Meta Pixel' => isset($signals['meta_pixel']) ? $yes($signals['meta_pixel']) : null,
                    'Google tag' => isset($signals['google_tag']) ? $yes($signals['google_tag']) : null,
                    'Google Ads tag' => isset($signals['google_ads_tag']) ? $yes($signals['google_ads_tag']) : null,
                    'Mobile-ready tag' => isset($signals['mobile_viewport_tag']) ? $yes($signals['mobile_viewport_tag']) : null,
                    'Contact form' => isset($signals['has_form']) ? $yes($signals['has_form']) : null,
                    'Booking tool' => ! empty($signals['booking_tools']) ? implode(', ', (array) $signals['booking_tools']) : (isset($signals['booking_tools']) ? 'None found' : null),
                    'Chat widget' => isset($signals['chat_widget']) ? (is_string($signals['chat_widget']) ? $signals['chat_widget'] : $yes($signals['chat_widget'])) : null,
                    'Video' => isset($signals['has_video']) ? $yes($signals['has_video']) : null,
                    'Copyright year' => $signals['copyright_year'] ?? null,
                    'Facebook' => $signals['facebook_page'] ?? null,
                    'Instagram' => $signals['instagram'] ?? null,
                    'Emails found' => ! empty($signals['emails']) ? implode(', ', (array) $signals['emails']) : null,
                ], fn ($v) => ! is_null($v) && $v !== '');
            @endphp
            <details class="scan-details">
                <summary>Show what the scan saw</summary>
                <dl class="facts">
                    @foreach ($scan as $label => $value)
                        <dt>{{ $label }}</dt><dd>@if (is_string($value) && str_starts_with($value, 'http'))<a href="{{ $value }}" target="_blank" rel="noopener">{{ Str::limit(preg_replace('#^https?://(www\.)?#', '', $value), 40) }}</a>@else{{ $value }}@endif</dd>
                    @endforeach
                </dl>
            </details>
        @endif
    @else
        <div class="panel__section caption">
            @if ($lead->audit_status === 'queued')
                Audit in progress. This page refreshes on its own.
            @elseif ($lead->audit_status === 'failed')
                Last audit failed: {{ $lead->audit_error }}
            @else
                This business hasn't been audited yet.
            @endif
        </div>
    @endif

    @php $draft = $lead->latestDraft; @endphp
    @if ($draft || ($lead->score >= config('cloyne.outreach.min_score') && $contact?->email))
        <div class="panel__section" style="display: flex; flex-direction: column; gap: 8px">
            <div class="label">First email</div>
            @if ($draft)
                <div class="split">
                    <span>{{ $draft->subject ?: $draft->statusLabel() }}</span>
                    <x-pill :tone="['approved' => 'success', 'failed' => 'danger', 'writing' => 'signal', 'pending' => 'signal'][$draft->status] ?? 'neutral'">{{ $draft->statusLabel() }}</x-pill>
                </div>
                @if ($draft->status !== 'writing')
                    <a href="{{ route('review', ['draft' => $draft->id]) }}" style="font-weight: 600">Open in Review queue</a>
                @endif
            @else
                <span class="caption">Hot lead with an email. Cloyne can write the first email from this audit.</span>
            @endif
        </div>
    @endif

    @can('edit')
    <div class="btn-row" style="padding: 16px">
        @if (\App\Services\Claude::isConfigured())
            <form method="post" action="{{ route('audits.one', $lead) }}">
                @csrf
                <button type="submit" @class(['btn', 'btn--secondary' => $audit && ! $stale, 'btn--primary' => ! $audit || $stale]) data-busy="Auditing…">{{ $audit ? 'Re-run audit' : 'Audit now' }}</button>
            </form>
        @else
            <span class="caption">Add <span class="mono">ANTHROPIC_API_KEY</span> to <span class="mono">.env</span> to audit.</span>
        @endif
        @if ($audit && \App\Services\Claude::isConfigured() && $contact?->email && $lead->stage !== 'excluded'
            && (! $draft || in_array($draft->status, ['skipped', 'failed'], true)))
            <form method="post" action="{{ route('drafts.write', $lead) }}">
                @csrf
                <button type="submit" class="btn btn--secondary" data-busy="Writing…">Write email</button>
            </form>
        @endif
        <form method="post" action="{{ route('leads.destroy', $lead) }}"
              data-confirm="Delete {{ $lead->displayName() }}? This can’t be undone, and it won’t come back in future searches. To keep a record, exclude it instead."
              data-confirm-button="Delete lead">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn--ghost">Delete</button>
        </form>
    </div>
    @if ($lead->stage !== 'excluded')
        <form method="post" action="{{ route('leads.exclude', $lead) }}" class="exclude-form">
            @csrf
            <label for="exclude-reason" class="label">Exclude instead</label>
            <div class="input-row">
                <select id="exclude-reason" name="reason" class="select">
                    @foreach (\App\Models\Business::EXCLUDE_REASONS as $reason)
                        <option value="{{ $reason }}">{{ $reason }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn--secondary">Exclude</button>
            </div>
            <span class="caption">Keeps the lead and its history, but stops audits, emails and calls.</span>
        </form>
    @endif
    @endcan
</aside>
