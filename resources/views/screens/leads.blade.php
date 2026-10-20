@extends('layouts.app')

@section('title', 'Leads')
@section('search', true)

@section('content')
    <div class="page-head">
        <div>
            <h1 class="page-title">Leads</h1>
            <div class="page-sub">{{ number_format($total) }} businesses · {{ number_format($hotCount) }} scored 60+</div>
        </div>
        <div class="btn-row">
            <button type="button" class="btn btn--secondary" disabled>Export</button>
            <button type="button" class="btn btn--primary" disabled>Add to campaign</button>
        </div>
    </div>

    <form class="filters" method="get" action="{{ route('leads') }}">
        @if (! empty($filters['q']))
            <input type="hidden" name="q" value="{{ $filters['q'] }}">
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
            <label for="f-package" class="label">Best package</label>
            <select id="f-package" name="package" class="select">
                <option value="">Any package</option>
                @foreach ($packages as $key => $package)
                    <option value="{{ $key }}" @selected(($filters['package'] ?? '') === $key)>{{ $package['name'] }}</option>
                @endforeach
            </select>
        </div>
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
                <p class="muted" style="margin: 0">No businesses yet. Start a search, or load the sample leads by running <span class="mono">php artisan db:seed</span>.</p>
                <a href="{{ route('finder') }}" class="btn btn--primary">Start a search</a>
            @endif
        </section>
    @else
        <div class="row">
            <div class="card card--flush grow-main">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Business</th><th>Category</th><th>Score</th><th>Best package</th><th>Email</th><th>Stage</th></tr></thead>
                        <tbody>
                        @foreach ($leads as $lead)
                            <tr @class(['is-selected' => $selected && $selected->id === $lead->id])>
                                <td>
                                    <a href="{{ request()->fullUrlWithQuery(['lead' => $lead->id]) }}" class="table__name" style="color: var(--ink); text-decoration: none">{{ $lead->name }}</a>
                                    <div class="caption">{{ $lead->location() }}</div>
                                </td>
                                <td>{{ $lead->category }}</td>
                                <td>
                                    @if (is_null($lead->score))
                                        <span class="caption">Not audited</span>
                                    @else
                                        <x-score :value="$lead->score" />
                                    @endif
                                </td>
                                <td>{{ $lead->packageName() ?? '—' }}</td>
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
@endsection
