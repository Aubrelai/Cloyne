@extends('layouts.app')

@section('title', 'Lead Finder')

@section('content')
    <div>
        <h1 class="page-title">Lead Finder</h1>
        <div class="page-sub">Pick a type of business and an area. Cloyne searches Google Maps and adds every business it finds to your Leads.</div>
    </div>

    @if (! $configured)
        <section class="card" style="border-color: var(--warning)">
            <div class="split"><h2 class="card__title">Connect Apify to start searching</h2><x-pill tone="warning">Not connected</x-pill></div>
            <div class="caption">Copy your API token from Apify (Settings → API &amp; Integrations), then add this line to the <span class="mono">.env</span> file in your Cloyne folder and restart Cloyne:</div>
            <div class="mono" style="padding: 10px 12px; border-radius: var(--radius-sm); background: var(--surface-raised)">APIFY_TOKEN=your_token_here</div>
        </section>
    @endif

    @cannot('edit')
        <div class="caption">You're a Viewer: you can see searches and leads, but not start or delete them.</div>
    @endcannot

    @can('edit')
    <form class="card" method="post" action="{{ route('searches.store') }}" id="search-form">
        @csrf
        <h2 class="card__title">New search</h2>

        @if ($errors->any())
            <div role="alert" style="display: flex; flex-direction: column; gap: 4px">
                @foreach ($errors->all() as $error)
                    <span><x-pill tone="danger">Error</x-pill> {{ $error }}</span>
                @endforeach
            </div>
        @endif

        @if ($repeat = session('repeat'))
            <div class="card" style="border-color: var(--warning); background: var(--warning-soft)" role="alert">
                <div class="split" style="flex-wrap: wrap">
                    <div>
                        <strong>You already searched {{ $repeat['label'] }} {{ $repeat['when'] }}</strong>
                        <div class="caption">{{ is_null($repeat['found']) ? 'It may still be running.' : number_format($repeat['found']) . ' businesses found then.' }} Searching again costs the same and mostly finds the same businesses.</div>
                    </div>
                    <button type="submit" name="repeat_ok" value="1" class="btn btn--secondary" data-busy="Starting…">Search again anyway</button>
                </div>
            </div>
        @endif

        <div class="stats">
            <div class="field">
                <label for="category" class="label">Type of business</label>
                <input id="category" name="category" type="text" class="input" list="category-ideas" required maxlength="80"
                       value="{{ old('category') }}" placeholder="e.g. dentists, med spas, roofers">
                <datalist id="category-ideas">
                    <option value="Dentists"></option><option value="Med spas"></option><option value="Restaurants"></option>
                    <option value="Gyms"></option><option value="Law firms"></option><option value="Real estate agents"></option>
                    <option value="Auto repair"></option><option value="Hair salons"></option><option value="Chiropractors"></option>
                    <option value="Roofers"></option><option value="Plumbers"></option><option value="HVAC"></option><option value="Landscapers"></option>
                </datalist>
            </div>
            <div class="field">
                <label for="area" class="label">Area</label>
                <input id="area" name="area" type="text" class="input" required maxlength="120" list="area-ideas"
                       value="{{ old('area') }}" placeholder="e.g. Dallas, TX or Palm Beach County, FL">
                <datalist id="area-ideas">
                    @foreach ($recentAreas as $area)
                        <option value="{{ $area }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div class="field">
                <label for="max_results" class="label">Max businesses</label>
                <select id="max_results" name="max_results" class="select" data-cost-per="{{ $pricePerResult }}" data-cost-target="search-cost">
                    @foreach ($maxOptions as $option)
                        <option value="{{ $option }}" @selected((int) old('max_results', 100) === $option)>{{ number_format($option) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="split" style="border-top: 1px solid var(--border); padding-top: 16px; flex-wrap: wrap;">
            <span class="caption">
                <strong id="search-cost">Up to {{ number_format(old('max_results', 100)) }} businesses · about ${{ number_format(old('max_results', 100) * $pricePerResult, 2) }}</strong>
                · Apify charges per business found; check usage at console.apify.com.
            </span>
            <button type="submit" class="btn btn--primary" @disabled(! $configured) data-busy="Starting…">Start search</button>
        </div>
    </form>
    @endcan

    <section class="card card--flush">
        <div class="split" style="padding: 16px 16px 8px">
            <div class="card__title">Searches</div>
            @if ($searches->contains->isRunning())
                <form method="post" action="{{ route('searches.check') }}">
                    @csrf
                    <button type="submit" class="btn btn--secondary btn--sm" data-busy="Checking…">Check now</button>
                </form>
            @endif
        </div>

        @if ($searches->isEmpty())
            <div class="caption" style="padding: 0 16px 16px">No searches yet. Start one above.</div>
        @else
            <div class="table-wrap">
                <table class="table" style="min-width: 760px">
                    <thead><tr><th>Search</th><th>Found</th><th>New leads</th><th>Audited</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($searches as $search)
                        @php
                            $skipped = collect([
                                $search->skipped_existing ? number_format($search->skipped_existing) . ' already in Cloyne' : null,
                                $search->skipped_dismissed ? number_format($search->skipped_dismissed) . ' deleted before' : null,
                                $search->skipped_closed ? number_format($search->skipped_closed) . ' closed' : null,
                            ])->filter();
                        @endphp
                        <tr>
                            <td>
                                <div class="table__name">{{ $search->label() }}</div>
                                <div class="caption">Up to {{ number_format($search->max_results) }} · started <x-time :at="$search->created_at" /></div>
                            </td>
                            <td class="mono">{{ is_null($search->found) ? '—' : number_format($search->found) }}</td>
                            <td>
                                <span class="mono brand-text">{{ is_null($search->added) ? '—' : number_format($search->added) }}</span>
                                @if ($skipped->isNotEmpty())
                                    <div class="caption">{{ $skipped->implode(' · ') }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($search->status === 'done' && $search->businesses_count > 0)
                                    <span class="mono">{{ number_format($search->audited_count) }}</span><span class="caption"> of {{ number_format($search->businesses_count) }}</span>
                                    @if ($search->hot_count > 0)
                                        <div class="caption"><span class="brand-text">{{ number_format($search->hot_count) }} hot</span></div>
                                    @endif
                                @else
                                    <span class="caption">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($search->status === 'running')
                                    <x-pill tone="signal">Searching</x-pill>
                                    <div class="caption" style="margin-top: 4px">{{ $search->minutesRunning() < 1 ? 'Just started' : $search->minutesRunning() . ' min so far' }} · usually 5–15 min</div>
                                    @if ($search->error)
                                        <div class="caption" style="margin-top: 4px">{{ $search->error }}</div>
                                    @endif
                                @elseif ($search->status === 'done')
                                    <x-pill tone="success">Done</x-pill>
                                @else
                                    <x-pill tone="danger">Failed</x-pill>
                                    <div class="caption" style="margin-top: 4px">{{ $search->error }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="btn-row" style="justify-content: flex-end; align-items: center">
                                    @if ($search->status === 'done' && $search->businesses_count)
                                        <a href="{{ route('leads', ['search' => $search->id]) }}" style="font-weight: 600">View leads</a>
                                    @endif
                                    @can('edit')
                                        @if (! $search->isRunning() && $configured)
                                            <form method="post" action="{{ route('searches.again', $search) }}"
                                                  data-confirm="Run {{ $search->label() }} again for up to {{ number_format($search->max_results) }} businesses? Apify charges again; businesses already in Cloyne aren't added twice."
                                                  data-confirm-button="Run again">
                                                @csrf
                                                <button type="submit" class="btn btn--ghost btn--sm" data-busy="Starting…">Run again</button>
                                            </form>
                                        @endif
                                        @php $leadCount = $search->businesses_count; @endphp
                                        <form method="post" action="{{ route('searches.destroy', $search) }}"
                                              data-confirm="Delete the search {{ $search->label() }}{{ $leadCount ? ' and its ' . number_format($leadCount) . ' ' . Str::plural('lead', $leadCount) : '' }}? This can’t be undone."
                                              data-confirm-button="Delete search">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn--ghost btn--sm">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($searches->contains->isRunning())
        {{-- Refresh every 30 seconds while a search is running, so results appear by themselves. --}}
        <meta http-equiv="refresh" content="30">
    @endif
@endsection
