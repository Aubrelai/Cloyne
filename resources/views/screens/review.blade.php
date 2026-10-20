@extends('layouts.app')

@section('title', 'Review queue')

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Review queue</h1>
            <div class="page-sub">
                {{ number_format($counts['pending'] ?? 0) }} waiting · {{ number_format($counts['approved'] ?? 0) }} approved · Nothing sends until a person approves it.
            </div>
        </div>
        <div class="btn-row" style="align-items: center">
            @if ($writing > 0)
                <span class="caption">{{ number_format($writing) }} being written in the background</span>
            @endif
            @can('edit')
                @if ($claudeConnected && $draftable > 0)
                    <form method="post" action="{{ route('drafts.write-many') }}">
                        @csrf
                        <button type="submit" class="btn btn--primary">Write {{ number_format(min($draftable, 200)) }} {{ Str::plural('email', $draftable) }} for hot leads</button>
                    </form>
                @elseif (! $claudeConnected)
                    <span class="caption">Add <span class="mono">ANTHROPIC_API_KEY</span> to <span class="mono">.env</span> to write emails</span>
                @endif
            @endcan
        </div>
    </div>

    @unless ($readyToSend)
        <section class="card" style="border-color: var(--warning)">
            <div class="split"><h2 class="card__title">Add a mailing address before sending</h2><x-pill tone="warning">Missing</x-pill></div>
            <div class="caption">The law (CAN-SPAM) requires a real postal address in every email. A PO box or virtual mailbox is fine. You can review and approve drafts now; add this line to <span class="mono">.env</span> before sending starts in Step 5:</div>
            <div class="mono" style="padding: 10px 12px; border-radius: var(--radius-sm); background: var(--surface-raised)">CLOYNE_MAILING_ADDRESS="LaiQ Digital, street, city, state ZIP"</div>
        </section>
    @endunless

    @if ((int) $counts->sum() === 0)
        <x-coming-soon title="No drafts yet" step="How drafts start">
            @if ($draftable > 0)
                {{ number_format($draftable) }} hot {{ Str::plural('lead', $draftable) }} with an email {{ $draftable === 1 ? 'is' : 'are' }} ready. Click "Write emails for hot leads" above, or open a lead and click "Write email".
            @else
                Drafts are written for hot leads (score {{ $outreach['min_score'] }}+) that have an email address. Run a search in the Lead Finder and audit the leads; hot ones get a draft automatically.
            @endif
        </x-coming-soon>
    @else
        <div class="row">
            <section class="card card--flush queue-list">
                <nav class="tabs" aria-label="Draft status">
                    @foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'skipped' => 'Skipped', 'failed' => 'Failed'] as $key => $label)
                        @if ($key !== 'failed' || ($counts['failed'] ?? 0) > 0)
                            <a href="{{ route('review', ['status' => $key]) }}" @class(['tab', 'is-active' => $status === $key]) @if ($status === $key) aria-current="page" @endif>
                                {{ $label }} <span class="tab__count">{{ number_format($counts[$key] ?? 0) }}</span>
                            </a>
                        @endif
                    @endforeach
                </nav>
                @forelse ($drafts as $item)
                    <a href="{{ route('review', ['status' => $status, 'draft' => $item->id, 'page' => $drafts->currentPage()]) }}" @class(['list-item', 'is-selected' => $selected && $selected->id === $item->id])>
                        <div class="split">
                            <strong>{{ $item->business->name }}</strong>
                            @if (! is_null($item->business->score))
                                <x-score :value="$item->business->score" />
                            @endif
                        </div>
                        <div class="caption" style="margin-top: 2px">{{ $item->subject ?: ($item->error ? 'Failed: ' . Str::limit($item->error, 60) : 'No subject') }}</div>
                    </a>
                @empty
                    @php
                        $emptyText = ['pending' => 'Nothing waiting. You’re all caught up.', 'approved' => 'No approved emails yet.', 'skipped' => 'Nothing skipped.'][$status] ?? 'No failed drafts.';
                    @endphp
                    <div class="caption" style="padding: 16px">{{ $emptyText }}</div>
                @endforelse
                @if ($drafts->hasPages())
                    <div class="split" style="padding: 8px 12px; border-top: 1px solid var(--border)">
                        <span class="caption">Page {{ $drafts->currentPage() }} of {{ $drafts->lastPage() }}</span>
                        <div class="btn-row">
                            @if ($drafts->previousPageUrl())<a href="{{ $drafts->previousPageUrl() }}" class="btn btn--secondary btn--sm">Previous</a>@endif
                            @if ($drafts->nextPageUrl())<a href="{{ $drafts->nextPageUrl() }}" class="btn btn--secondary btn--sm">Next</a>@endif
                        </div>
                    </div>
                @endif
            </section>

            @if ($selected)
                @include('screens.partials.draft-editor', ['draft' => $selected])
            @endif
        </div>
    @endif

    @if ($writing > 0)
        {{-- Refresh every 20 seconds while emails are being written, unless someone is typing. --}}
        <script>
            setTimeout(function check() {
                if (document.querySelector('[data-dirty="1"]')) { setTimeout(check, 20000); return; }
                location.reload();
            }, 20000);
        </script>
    @endif
@endsection
