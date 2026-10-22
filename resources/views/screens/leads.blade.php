@extends('layouts.app')

@section('title', 'Leads')
@section('search', true)

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Leads</h1>
            <div class="page-sub">{{ number_format($total) }} businesses · {{ number_format($hotCount) }} scored {{ $hotScore }}+@if ($excludedCount) · <a href="{{ route('leads', ['stage' => 'excluded']) }}">{{ number_format($excludedCount) }} excluded</a>@endif</div>
            @if ($fromSearch)
                <div class="caption" style="margin-top: 4px">Showing leads from <strong>{{ $fromSearch->label() }}</strong> · <a href="{{ route('leads', ['clear' => 1]) }}">Show all</a></div>
            @endif
        </div>
        <div class="btn-row" style="align-items: center">
            @if ($queued > 0)
                <span class="caption">{{ number_format($queued) }} auditing in the background</span>
            @endif
            @if (auth()->user()->cannot('edit'))
                {{-- Viewers can't start audits. --}}
            @elseif ($claudeConnected && $unaudited > 0)
                <form method="post" action="{{ route('audits.many') }}">
                    @csrf
                    @if (! empty($filters['search']))
                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                    @endif
                    <button type="submit" class="btn btn--primary" data-busy="Queuing…">Audit {{ number_format(min($unaudited, 500)) }} {{ Str::plural('lead', $unaudited) }}</button>
                </form>
            @elseif (! $claudeConnected)
                <span class="caption">Add <span class="mono">ANTHROPIC_API_KEY</span> to <span class="mono">.env</span> to audit leads</span>
            @endif
        </div>
    </div>

    @if ($views->isNotEmpty() || array_filter($filters))
        <div class="views" aria-label="Saved views">
            <span class="label">Views</span>
            @foreach ($views as $view)
                <span @class(['view-chip', 'is-active' => $activeView === $view->id])>
                    <a href="{{ $view->url() }}">{{ $view->name }}</a>
                    <form method="post" action="{{ route('views.destroy', $view) }}" data-confirm="Remove the saved view “{{ $view->name }}”? Your leads stay as they are." data-confirm-button="Remove view">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="view-chip__remove" aria-label="Remove view {{ $view->name }}">×</button>
                    </form>
                </span>
            @endforeach
            @if (array_filter($filters) && ! $activeView)
                <details class="save-view">
                    <summary class="btn btn--ghost btn--sm">Save these filters</summary>
                    <form method="post" action="{{ route('views.store') }}" class="save-view__form">
                        @csrf
                        @foreach (array_filter($filters) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}"><input type="hidden" name="per" value="{{ $per }}">
                        <label for="view-name" class="sr-only">View name</label>
                        <input id="view-name" name="name" class="input" maxlength="60" required placeholder="e.g. Hot med spas, FL">
                        <button type="submit" class="btn btn--secondary btn--sm">Save view</button>
                    </form>
                </details>
            @elseif ($views->isEmpty())
                <span class="caption">Pick filters below, then save them for one-click access.</span>
            @endif
        </div>
    @endif

    <form class="filters" method="get" action="{{ route('leads') }}">
        <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}"><input type="hidden" name="per" value="{{ $per }}">
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
                <option value="hot" @selected(($filters['score'] ?? '') === 'hot')>{{ $hotScore }}+ (hot)</option>
                <option value="warm" @selected(($filters['score'] ?? '') === 'warm')>40–{{ $hotScore - 1 }} (warm)</option>
                <option value="cold" @selected(($filters['score'] ?? '') === 'cold')>Under 40 (cold)</option>
            </select>
        </div>
        <div class="field">
            <label for="f-email" class="label">Email</label>
            <select id="f-email" name="email" class="select">
                <option value="">Any status</option>
                <option value="any" @selected(($filters['email'] ?? '') === 'any')>Has an email</option>
                @foreach (\App\Models\Contact::EMAIL_STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['email'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
                <option value="none" @selected(($filters['email'] ?? '') === 'none')>Not found</option>
            </select>
        </div>
        <div class="field">
            <label for="f-stage" class="label">Stage</label>
            <select id="f-stage" name="stage" class="select">
                <option value="">Active (not excluded)</option>
                @foreach (\App\Models\Business::STAGES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['stage'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if (! empty($filters['audited']))
            <input type="hidden" name="audited" value="{{ $filters['audited'] }}">
        @endif
        <button type="submit" class="btn btn--secondary">Apply</button>
        @if (array_filter($filters))
            <a href="{{ route('leads', ['clear' => 1]) }}" class="btn btn--ghost">Clear</a>
        @endif
    </form>

    @if ($leads->isEmpty())
        <section class="card" style="align-items: flex-start">
            <h2 class="card__title">No leads match</h2>
            @if (array_filter($filters))
                <p class="muted" style="margin: 0">Try clearing some filters.</p>
                <a href="{{ route('leads', ['clear' => 1]) }}" class="btn btn--secondary">Clear filters</a>
            @else
                <p class="muted" style="margin: 0">No businesses yet. Run a search in the Lead Finder and they'll show up here.</p>
                @can('edit')<a href="{{ route('finder') }}" class="btn btn--primary">Start a search</a>@endcan
            @endif
        </section>
    @else
        <form id="bulk" method="post" action="{{ route('leads.destroy-many') }}">
            @csrf
        </form>
        <div class="row">
            <div class="card card--flush grow-main">
                @can('edit')
                <div class="bulk-bar" style="padding: 8px 12px; border-bottom: 1px solid var(--border)">
                    <span class="caption" id="selected-count">Tick leads to audit, write emails, exclude or delete them</span>
                    <div class="btn-row" style="align-items: center">
                        @if ($claudeConnected)
                            <button type="submit" form="bulk" formaction="{{ route('audits.selected') }}" class="btn btn--ghost btn--sm bulk-action" disabled data-busy="Queuing…">Audit</button>
                            <button type="submit" form="bulk" formaction="{{ route('drafts.write-selected') }}" class="btn btn--ghost btn--sm bulk-action" disabled data-busy="Queuing…">Write emails</button>
                        @endif
                        <label for="bulk-reason" class="sr-only">Reason for excluding</label>
                        <select id="bulk-reason" name="reason" form="bulk" class="select select--sm bulk-action" disabled>
                            @foreach (\App\Models\Business::EXCLUDE_REASONS as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                        <button type="submit" form="bulk" formaction="{{ route('leads.exclude-many') }}" class="btn btn--ghost btn--sm bulk-action" disabled>Exclude</button>
                        <button type="submit" form="bulk" class="btn btn--ghost btn--sm bulk-action" id="delete-selected" disabled
                                data-confirm="Delete the selected leads? This can’t be undone, and they won’t come back in future searches. To keep a record, exclude them instead." data-confirm-button="Delete leads">Delete</button>
                        @if ($matching > 0)
                            <form method="post" action="{{ route('leads.destroy-matching') }}"
                                  data-confirm="Delete all {{ number_format($matching) }} {{ Str::plural('lead', $matching) }} matching these filters? This can’t be undone, and they won’t come back in future searches."
                                  data-confirm-button="Delete {{ number_format($matching) }} {{ Str::plural('lead', $matching) }}">
                                @csrf
                                @foreach (array_filter($filters) as $key => $value)
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endforeach
                                <button type="submit" class="btn btn--secondary btn--sm">Delete all {{ number_format($matching) }} matching</button>
                            </form>
                        @endif
                    </div>
                </div>
                @endcan
                <div class="table-wrap">
                    <table class="table">
                        @php
                            $sortLink = function ($key, $label) use ($sort, $dir) {
                                $active = $sort === $key;
                                $next = $active ? ($dir === 'desc' ? 'asc' : 'desc') : ($key === 'name' ? 'asc' : 'desc');
                                $arrow = $active ? ($dir === 'desc' ? ' ↓' : ' ↑') : '';
                                $aria = $active ? ($dir === 'desc' ? 'descending' : 'ascending') : 'none';
                                return ['url' => request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]), 'label' => $label . $arrow, 'aria' => $aria];
                            };
                        @endphp
                        <thead><tr>
                            @can('edit')<th style="width: 32px"><input type="checkbox" id="select-all" aria-label="Select all leads on this page"></th>@endcan
                            @foreach (['name' => 'Business', 'reviews' => 'Reviews', 'score' => 'Score'] as $key => $label)
                                @php $link = $sortLink($key, $label); @endphp
                                <th aria-sort="{{ $link['aria'] }}"><a href="{{ $link['url'] }}" class="sort-link">{{ $link['label'] }}</a></th>
                            @endforeach
                            <th>Best fit</th><th>Pitch now</th><th>Email</th>
                            @php $link = $sortLink('newest', 'Added'); @endphp
                            <th aria-sort="{{ $link['aria'] }}"><a href="{{ $link['url'] }}" class="sort-link">{{ $link['label'] }}</a></th>
                        </tr></thead>
                        <tbody data-key-list data-next="ArrowDown" data-prev="ArrowUp">
                        @foreach ($leads as $lead)
                            <tr data-key-item @class(['is-selected' => $selected && $selected->id === $lead->id])>
                                @can('edit')<td><input type="checkbox" name="ids[]" value="{{ $lead->id }}" form="bulk" class="lead-check" aria-label="Select {{ $lead->displayName() }}"></td>@endcan
                                <td>
                                    <a href="{{ request()->fullUrlWithQuery(['lead' => $lead->id]) }}" class="table__name" style="color: var(--ink); text-decoration: none">{{ $lead->displayName() }}</a>
                                    @if ($lead->is_chain)<x-pill tone="warning">Chain</x-pill>@endif
                                    <div class="caption">{{ collect([$lead->category, $lead->location()])->filter()->implode(' · ') }}</div>
                                </td>
                                <td class="nowrap">@if ($lead->rating)<span class="mono">{{ $lead->rating }}</span> <span class="star" aria-hidden="true">★</span> <span class="caption">{{ number_format($lead->review_count) }}</span>@else<span class="caption">—</span>@endif</td>
                                <td>
                                    @if ($lead->audit_status === 'queued')
                                        <x-pill tone="signal">Auditing</x-pill>
                                    @elseif ($lead->audit_status === 'failed' && is_null($lead->score))
                                        <x-pill tone="danger">Failed</x-pill>
                                    @elseif (is_null($lead->score))
                                        <span class="caption">Not audited</span>
                                    @else
                                        <x-score :value="$lead->score" :checks="$lead->latestAudit?->checks" :hot="$hotScore" />
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
                                <td class="caption nowrap"><x-time :at="$lead->created_at" /><div>{{ $lead->stageLabel() }}</div></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($leads->total() > 25)
                    <div class="split" style="padding: 12px 16px; border-top: 1px solid var(--border); flex-wrap: wrap">
                        <span class="caption">
                            Showing {{ $leads->firstItem() }}–{{ $leads->lastItem() }} of {{ number_format($leads->total()) }} ·
                            @foreach (\App\Http\Controllers\ScreenController::PER_PAGE as $option)
                                @if ($option === $per)<strong>{{ $option }}</strong>@else<a href="{{ request()->fullUrlWithQuery(['per' => $option, 'page' => null]) }}">{{ $option }}</a>@endif{{ $loop->last ? ' per page' : ' · ' }}
                            @endforeach
                        </span>
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

    @can('edit')
    <script>
        // Bulk actions: switch on the buttons and count once leads are ticked.
        (function () {
            const all = document.getElementById('select-all');
            const boxes = Array.from(document.querySelectorAll('.lead-check'));
            const actions = Array.from(document.querySelectorAll('.bulk-action'));
            const label = document.getElementById('selected-count');
            if (!all || !label) return;
            const update = () => {
                const n = boxes.filter(b => b.checked).length;
                actions.forEach(a => { a.disabled = n === 0; });
                label.textContent = n ? n + (n === 1 ? ' lead selected' : ' leads selected') : 'Tick leads to audit, write emails, exclude or delete them';
                all.checked = n > 0 && n === boxes.length;
                all.indeterminate = n > 0 && n < boxes.length;
            };
            all.addEventListener('change', () => { boxes.forEach(b => b.checked = all.checked); update(); });
            boxes.forEach(b => b.addEventListener('change', update));
            update();
        })();
    </script>
    @endcan
    @if ($queued > 0)
        {{-- Refresh every 20 seconds while audits are running, so scores appear by themselves. --}}
        <script>
            setTimeout(() => { if (!document.querySelector('.lead-check:checked')) location.reload(); }, 20000);
        </script>
    @endif
@endsection
