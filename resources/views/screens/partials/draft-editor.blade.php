@php
    $lead = $draft->business;
    $audit = $lead->latestAudit;
    $packages = \App\Models\Business::packages();
    $canEdit = auth()->user()->can('edit');
    $editable = $canEdit && in_array($draft->status, ['pending', 'approved'], true);
    $statusTone = ['approved' => 'success', 'failed' => 'danger', 'skipped' => 'neutral'][$draft->status] ?? 'signal';
    $warnings = in_array($draft->status, ['pending', 'approved'], true) ? $draft->warnings() : [];
    $claim = in_array($draft->status, ['pending', 'approved'], true) ? $draft->claimedFinding() : null;
    $topChecks = collect($audit?->checks ?? [])->sortByDesc('points')->take(3);
    $maxWords = (int) config('cloyne.outreach.max_words', 120);
@endphp
<section class="card grow-main" aria-label="Email draft">
    <div class="split" style="align-items: flex-start; flex-wrap: wrap">
        <div>
            <h2 class="card__title">{{ $lead->displayName() }}</h2>
            <div class="caption" style="margin-top: 2px">
                {{ collect([$lead->category, $lead->location(), $draft->package ? 'Pitch: ' . ($packages[$draft->package]['name'] ?? $draft->package) : null])->filter()->implode(' · ') }}
            </div>
        </div>
        <div class="btn-row" style="align-items: center">
            @if ($draft->edited)<x-pill tone="neutral">Edited</x-pill>@endif
            <x-pill :tone="$statusTone">{{ $draft->status === 'pending' ? 'AI drafted' : $draft->statusLabel() }}</x-pill>
            <a href="{{ route('leads', ['lead' => $lead->id]) }}" class="btn btn--ghost btn--sm">Open lead</a>
            @if ($lead->phone)<x-phone :number="$lead->phone" :copy="false" />@endif
        </div>
    </div>

    @if ($draft->angle || $audit?->pitch)
        <div class="finding"><strong>Built on:</strong> {{ $draft->angle ?: $audit->pitch }}</div>
    @endif

    @if ($topChecks->isNotEmpty())
        <div class="audit-facts" aria-label="Top audit findings">
            <div class="split"><span class="label">What the audit found</span>@if (! is_null($lead->score))<x-score :value="$lead->score" :checks="$audit?->checks" />@endif</div>
            @foreach ($topChecks as $check)
                <div class="audit-facts__row">
                    <span @class(['audit__icon', 'audit__icon--yes' => $check['passed'], 'audit__icon--no' => ! $check['passed']])>{{ $check['passed'] ? '✓' : '✗' }}</span>
                    <span><strong>{{ $check['title'] }}</strong> <span class="caption">{{ $check['detail'] }}</span></span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($draft->status === 'failed')
        <div role="alert"><x-pill tone="danger">Failed</x-pill> {{ $draft->error }}</div>
        @if ($canEdit)
            <form method="post" action="{{ route('drafts.rewrite', $draft) }}">
                @csrf
                <button type="submit" class="btn btn--primary" data-busy="Writing…">Try again</button>
            </form>
        @endif
    @else
        <dl class="facts">
            <dt>To</dt><dd class="mono">{{ $draft->to_email ?? '—' }}{{ $draft->contact?->name ? ' · ' . $draft->contact->name : '' }}</dd>
            <dt>From</dt><dd>{{ \App\Models\Draft::signature() === '' ? '—' : str_replace("\n", ', ', \App\Models\Draft::signature()) }}</dd>
            @if ($draft->reviewed_at)
                <dt>{{ $draft->status === 'skipped' ? 'Skipped' : 'Approved' }}</dt><dd>{{ $draft->reviewer?->name ?? 'Someone' }} · {{ $draft->reviewed_at->diffForHumans() }}</dd>
            @endif
        </dl>

        @if ($errors->hasAny(['subject', 'body']))
            <div role="alert"><x-pill tone="danger">Not saved</x-pill> {{ $errors->first('subject') ?: $errors->first('body') }}</div>
        @endif

        @foreach ($warnings as $warning)
            <div><x-pill tone="warning">Check</x-pill> {{ $warning }}</div>
        @endforeach
        @if (in_array($draft->status, ['pending', 'approved'], true))
            @if ($claim)
                <div class="caption"><x-pill tone="success">Matches audit</x-pill> Talks about “{{ $claim['title'] }}”: {{ $claim['detail'] }}</div>
            @else
                <div><x-pill tone="warning">Check</x-pill> Doesn’t clearly mention a finding from the audit. Make sure the claim is true.</div>
            @endif
        @endif

        @if ($editable)
            <form id="draft-form" method="post" action="{{ route('drafts.update', $draft) }}" style="display: flex; flex-direction: column; gap: 12px" data-warn-unsaved>
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="subject" class="label">Subject</label>
                    <input id="subject" name="subject" type="text" class="input" required maxlength="120" value="{{ old('subject', $draft->subject) }}">
                </div>
                <div class="field" id="draft-compose">
                    <div class="split">
                        <label for="body" class="label">Email</label>
                        <span class="btn-row" style="align-items: center">
                            <span class="caption" id="word-count" aria-live="polite"></span>
                            <button type="button" class="btn btn--ghost btn--sm" data-toggle-class="is-phone" data-toggle-target="#draft-compose" aria-pressed="false">Phone size</button>
                        </span>
                    </div>
                    <textarea id="body" name="body" class="textarea textarea--email" required maxlength="5000" data-key-focus="e"
                              data-word-count="word-count" data-word-limit="{{ $maxWords }}">{{ old('body', $draft->body) }}</textarea>
                    <div class="email-preview email-preview__foot">{{ \App\Models\Draft::signature() }}

{{ \App\Models\Draft::footer() }}</div>
                </div>
            </form>
            <div class="split" style="border-top: 1px solid var(--border); padding-top: 16px; flex-wrap: wrap">
                <div class="btn-row" style="justify-content: flex-start">
                    @if ($draft->status === 'pending')
                        <form method="post" action="{{ route('drafts.skip', $draft) }}">
                            @csrf
                            <button type="submit" class="btn btn--ghost" data-key="s" title="Shortcut: S">Skip lead</button>
                        </form>
                        <form method="post" action="{{ route('drafts.rewrite', $draft) }}" class="rewrite-group"
                              data-confirm="Write a new version? Your unsaved edits will be lost." data-confirm-button="Rewrite" data-confirm-if="#draft-form[data-dirty='1']">
                            @csrf
                            <button type="submit" class="btn btn--ghost" data-key="r" title="Shortcut: R" data-busy="Writing…">Rewrite</button>
                            <button type="submit" name="style" value="shorter" class="btn btn--ghost btn--sm" data-busy="Writing…">Shorter</button>
                            <button type="submit" name="style" value="casual" class="btn btn--ghost btn--sm" data-busy="Writing…">More casual</button>
                            <button type="submit" name="style" value="angle" class="btn btn--ghost btn--sm" data-busy="Writing…">Different angle</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('drafts.reopen', $draft) }}">
                            @csrf
                            <button type="submit" class="btn btn--ghost">Move back to review</button>
                        </form>
                    @endif
                </div>
                <div class="btn-row">
                    <button type="submit" form="draft-form" class="btn btn--secondary">Save edits</button>
                    @if ($draft->status === 'pending')
                        <button type="submit" form="draft-form" formaction="{{ route('drafts.approve', $draft) }}" class="btn btn--primary" data-key="a" title="Shortcut: A" @disabled(! $draft->to_email)>Approve</button>
                    @endif
                </div>
            </div>
        @else
            <div class="email-preview"><strong>{{ $draft->subject }}</strong>

{{ $draft->body }}

<span class="email-preview__foot">{{ \App\Models\Draft::signature() }}

{{ \App\Models\Draft::footer() }}</span></div>
            @if ($canEdit && $draft->status === 'skipped')
                <form method="post" action="{{ route('drafts.reopen', $draft) }}">
                    @csrf
                    <button type="submit" class="btn btn--secondary">Move back to review</button>
                </form>
            @endif
        @endif
    @endif
</section>
