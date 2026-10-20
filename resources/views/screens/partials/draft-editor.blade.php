@php
    $lead = $draft->business;
    $audit = $lead->latestAudit;
    $packages = \App\Models\Business::packages();
    $canEdit = auth()->user()->can('edit');
    $editable = $canEdit && in_array($draft->status, ['pending', 'approved'], true);
    $statusTone = ['approved' => 'success', 'failed' => 'danger', 'skipped' => 'neutral'][$draft->status] ?? 'signal';
    $warnings = in_array($draft->status, ['pending', 'approved'], true) ? $draft->warnings() : [];
@endphp
<section class="card grow-main" aria-label="Email draft">
    <div class="split" style="align-items: flex-start; flex-wrap: wrap">
        <div>
            <h2 class="card__title">{{ $lead->name }}</h2>
            <div class="caption" style="margin-top: 2px">
                {{ collect([$lead->category, $lead->location(), $draft->package ? 'Pitch: ' . ($packages[$draft->package]['name'] ?? $draft->package) : null])->filter()->implode(' · ') }}
            </div>
        </div>
        <div class="btn-row" style="align-items: center">
            @if ($draft->edited)<x-pill tone="neutral">Edited</x-pill>@endif
            <x-pill :tone="$statusTone">{{ $draft->status === 'pending' ? 'AI drafted' : $draft->statusLabel() }}</x-pill>
            <a href="{{ route('leads', ['lead' => $lead->id]) }}" class="btn btn--ghost btn--sm">Open lead</a>
        </div>
    </div>

    @if ($draft->angle || $audit?->pitch)
        <div class="finding"><strong>Built on:</strong> {{ $draft->angle ?: $audit->pitch }}</div>
    @endif

    @if ($draft->status === 'failed')
        <div role="alert"><x-pill tone="danger">Failed</x-pill> {{ $draft->error }}</div>
        @if ($canEdit)
            <form method="post" action="{{ route('drafts.rewrite', $draft) }}">
                @csrf
                <button type="submit" class="btn btn--primary">Try again</button>
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

        @if ($editable)
            <form id="draft-form" method="post" action="{{ route('drafts.update', $draft) }}" style="display: flex; flex-direction: column; gap: 12px"
                  oninput="this.dataset.dirty = '1'">
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="subject" class="label">Subject</label>
                    <input id="subject" name="subject" type="text" class="input" required maxlength="120" value="{{ old('subject', $draft->subject) }}">
                </div>
                <div class="field">
                    <label for="body" class="label">Email</label>
                    <textarea id="body" name="body" class="textarea textarea--email" required maxlength="5000">{{ old('body', $draft->body) }}</textarea>
                </div>
            </form>
            <div class="email-preview email-preview__foot">{{ \App\Models\Draft::signature() }}

{{ \App\Models\Draft::footer() }}</div>
            <div class="split" style="border-top: 1px solid var(--border); padding-top: 16px; flex-wrap: wrap">
                <div class="btn-row" style="justify-content: flex-start">
                    @if ($draft->status === 'pending')
                        <form method="post" action="{{ route('drafts.skip', $draft) }}">
                            @csrf
                            <button type="submit" class="btn btn--ghost">Skip lead</button>
                        </form>
                        <form method="post" action="{{ route('drafts.rewrite', $draft) }}"
                              onsubmit="return !document.querySelector('[data-dirty=&quot;1&quot;]') || confirm('Write a new version? Your edits will be lost.')">
                            @csrf
                            <button type="submit" class="btn btn--ghost">Rewrite</button>
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
                        <button type="submit" form="draft-form" formaction="{{ route('drafts.approve', $draft) }}" class="btn btn--primary" @disabled(! $draft->to_email)>Approve</button>
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
