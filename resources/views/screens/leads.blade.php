@extends('layouts.app')

@section('title', 'Leads')
@section('search', true)

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Leads</h1>
            <div class="page-sub">{{ number_format($total) }} businesses · {{ number_format($hotCount) }} scored 60+</div>
            @if ($fromSearch)
                <div class="caption" style="margin-top: 4px">Showing leads from <strong>{{ $fromSearch->label() }}</strong> · <a href="{{ route('leads') }}">Show all</a></div>
            @endif
        </div>
        <div class="btn-row" style="align-items: center">
            @if ($queued > 0)
                <span class="caption">{{ number_format($queued) }} auditing in the background</span>
            @endif
            @if ($claudeConnected && $unaudited > 0)
                <form method="post" action="{{ route('audits.many') }}">
                    @csrf
                    @if (! empty($filters['search']))
                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                    @endif
                    <button type="submit" class="btn btn--primary">Audit {{ number_format(min($unaudited, 500)) }} {{ Str::plural('lead', $unaudited) }}</button>
                </form>
            @elseif (! $claudeConnected)
                <span class="caption">Add <span class="mono">ANTHROPIC_API_KEY</span> to <span class="mono">.env</span> to audit leads</span>
            @endif
        </div>
    </div>

    <form class="filters" method="get" action="{{ route('leads') }}">
        @if (! empty($filters['q']))
            <input type="hidden" name="q" value="{{ $filters['q'] }}">
        @endif
        @if (! empty($filters['search']))
            <input type="hidden" name="search" value="{{ $filters['search'] }}">
        @endif
        <div class="field">
            <label for="f-category" class="label">Category</label>
            <select id="f-category" name="category" class="select">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="f-package" class="label">Best fit</label>
            <select id="f-package" name="package" class="select">
                <option value="">Any package</option>
                @foreach ($packages as $key => $package)
                    <option value="{{ $key }}" @selected(($filters['package'] ?? '') === $key)>{{ $package['name'] }}</option>
                @endforeach
            </select>
        </div>
        <label class="check">
            <input type="checkbox" name="pitchable" value="1" @checked(! empty($filters['pitchable']))>
            Can pitch now
        </label>
        <div class="field">
            <label for="f-state" class="label">State</label>
            <select id="f-state" name="state" class="select">
                <option value="">All states</option>
                @foreach ($states as $state)
                    <option value="{{ $state }}" @selected(($filters['state'] ?? '') === $state)>{{ $state }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="f-score" class="label">Score</label>
            <select id="f-score" name="score" class="select">
                <option value="">Any score</option>
                <option value="hot" @selected(($filters['score'] ?? '') === 'hot')>60+ (hot)</option>
                <option value="warm" @selected(($filters['score'] ?? '') === 'warm')>40–59 (warm)</option>
                <option value="cold" @selected(($filters['score'] ?? '') === 'cold')>Under 40 (cold)</option>
            </select>
        </div>
        <div class="field">
            <label for="f-email" class="label">Email</label>
            <select id="f-email" name="email" class="select">
                <option value="">Any status</option>
                @foreach (\App\Models\Contact::EMAIL_STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['email'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
                <option value="none" @selected(($filters['email'] ?? '') === 'none')>Not found</option>
            </select>
        </div>
        <button type="submit" class="btn btn--secondary">Apply</button>
        @if (array_filter($filters))
            <a href="{{ route('leads') }}" class="btn btn--ghost">Clear</a>
        @endif
    </form>

    @if ($leads->isEmpty())
        <section class="card" style="align-items: flex-start">
            <h2 class="card__title">No leads match</h2>
            @if (array_filter($filters))
                <p class="muted" style="margin: 0">Try clearing some filters.</p>
                <a href="{{ route('leads') }}" class="btn btn--secondary">Clear filters</a>
            @else
                <p class="muted" style="margin: 0">No businesses yet. Run a search in the Lead Finder and they'll show up here.</p>
                <a href="{{ route('finder') }}" class="btn btn--primary">Start a search</a>
            @endif
        </section>
    @else
        <form id="bulk-delete" method="post" action="{{ route('leads.destroy-many') }}"
              onsubmit="return confirm('Delete the selected leads? This can’t be undone, and they won’t come back in future searches.')">
            @csrf
        </form>
        <div class="row">
            <div class="card card--flush grow-main">
                <div class="split" style="padding: 8px 12px; border-bottom: 1px solid var(--border)">
                    <span class="caption" id="selected-count">Tick leads to delete them</span>
                    <button type="submit" form="bulk-delete" class="btn btn--ghost btn--sm" id="delete-selected" disabled>Delete selected</button>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th style="width: 32px"><input type="checkbox" id="select-all" aria-label="Select all leads on this page"></th><th>Business</th><th>Category</th><th>Score</th><th>Best fit</th><th>Pitch now</th><th>Email</th><th>Stage</th></tr></thead>
                        <tbody>
                        @foreach ($leads as $lead)
                            <tr @class(['is-selected' => $selected && $selected->id === $lead->id])>
                                <td><input type="checkbox" name="ids[]" value="{{ $lead->id }}" form="bulk-delete" class="lead-check" aria-label="Select {{ $lead->name }}"></td>
                                <td>
                                    <a href="{{ request()->fullUrlWithQuery(['lead' => $lead->id]) }}" class="table__name" style="color: var(--ink); text-decoration: none">{{ $lead->name }}</a>
                                    <div class="caption">{{ $lead->location() }}</div>
                                </td>
                                <td>{{ $lead->category }}</td>
                                <td>
                                    @if ($lead->audit_status === 'queued')
                                        <x-pill tone="signal">Auditing</x-pill>
                                    @elseif ($lead->audit_status === 'failed' && is_null($lead->score))
                                        <x-pill tone="danger">Failed</x-pill>
                                    @elseif (is_null($lead->score))
                                        <span class="caption">Not audited</span>
                                    @else
                                        <x-score :value="$lead->score" />
                                    @endif
                                </td>
                                <td>{{ $lead->packageShort() ?? '—' }}</td>
                                <td>
                                    @if ($lead->pitchPackageShort())
                                        <strong>{{ $lead->pitchPackageShort() }}</strong>
                                    @elseif ($lead->package)
                                        <span class="caption">On hold</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><x-pill>{{ $lead->primaryContact?->emailLabel() ?? 'Not found' }}</x-pill></td>
                                <td>{{ $lead->stageLabel() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($leads->hasPages())
                    <div class="split" style="padding: 12px 16px; border-top: 1px solid var(--border)">
                        <span class="caption">Showing {{ $leads->firstItem() }}–{{ $leads->lastItem() }} of {{ number_format($leads->total()) }}</span>
                        <div class="btn-row">
                            @if ($leads->onFirstPage())
                                <button type="button" class="btn btn--secondary btn--sm" disabled>Previous</button>
                            @else
                                <a href="{{ $leads->previousPageUrl() }}" class="btn btn--secondary btn--sm">Previous</a>
                            @endif
                            @if ($leads->hasMorePages())
                                <a href="{{ $leads->nextPageUrl() }}" class="btn btn--secondary btn--sm">Next</a>
                            @else
                                <button type="button" class="btn btn--secondary btn--sm" disabled>Next</button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if ($selected)
                @include('screens.partials.lead-profile', ['lead' => $selected])
            @endif
        </div>
    @endif

    <script>
        // Bulk delete: keep the button and count in step with the ticked boxes.
        (function () {
            const all = document.getElementById('select-all');
            const boxes = Array.from(document.querySelectorAll('.lead-check'));
            const button = document.getElementById('delete-selected');
            const label = document.getElementById('selected-count');
            if (!all || !button) return;
            const update = () => {
                const n = boxes.filter(b => b.checked).length;
                button.disabled = n === 0;
                button.textContent = n ? 'Delete ' + n + (n === 1 ? ' lead' : ' leads') : 'Delete selected';
                label.textContent = n ? n + ' selected' : 'Tick leads to delete them';
                all.checked = n > 0 && n === boxes.length;
            };
            all.addEventListener('change', () => { boxes.forEach(b => b.checked = all.checked); update(); });
            boxes.forEach(b => b.addEventListener('change', update));
        })();
    </script>
    @if ($queued > 0)
        {{-- Refresh every 20 seconds while audits are running, so scores appear by themselves. --}}
        <script>
            setTimeout(() => { if (!document.querySelector('.lead-check:checked')) location.reload(); }, 20000);
        </script>
    @endif
@endsection
