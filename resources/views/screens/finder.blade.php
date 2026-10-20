@extends('layouts.app')

@section('title', 'Lead Finder')

@section('content')
    <div>
        <h1 class="page-title">Lead Finder</h1>
        <div class="page-sub">Pick a type of business and an area. Cloyne searches Google Maps and adds every business it finds to your Leads.</div>
    </div>

    @if (session('status'))
        <div class="finding" role="status">{{ session('status') }}</div>
    @endif

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
    <form class="card" method="post" action="{{ route('searches.store') }}">
        @csrf
        <h2 class="card__title">New search</h2>

        @if ($errors->any())
            <div role="alert" style="display: flex; flex-direction: column; gap: 4px">
                @foreach ($errors->all() as $error)
                    <span><x-pill tone="danger">Error</x-pill> {{ $error }}</span>
                @endforeach
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
                <input id="area" name="area" type="text" class="input" required maxlength="120"
                       value="{{ old('area') }}" placeholder="e.g. Dallas, TX or Palm Beach County, FL">
            </div>
            <div class="field">
                <label for="max_results" class="label">Max businesses</label>
                <select id="max_results" name="max_results" class="select">
                    @foreach ($maxOptions as $option)
                        <option value="{{ $option }}" @selected((int) old('max_results', 100) === $option)>{{ number_format($option) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="split" style="border-top: 1px solid var(--border); padding-top: 16px; flex-wrap: wrap;">
            <span class="caption">Apify charges per business found. Start small (100) and check your usage at console.apify.com.</span>
            <button type="submit" class="btn btn--primary" @disabled(! $configured)>Start search</button>
        </div>
    </form>
    @endcan

    <section class="card card--flush">
        <div class="split" style="padding: 16px 16px 8px">
            <div class="card__title">Searches</div>
            @if ($searches->contains->isRunning())
                <form method="post" action="{{ route('searches.check') }}">
                    @csrf
                    <button type="submit" class="btn btn--secondary btn--sm">Check now</button>
                </form>
            @endif
        </div>

        @if ($searches->isEmpty())
            <div class="caption" style="padding: 0 16px 16px">No searches yet. Start one above.</div>
        @else
            <div class="table-wrap">
                <table class="table" style="min-width: 720px">
                    <thead><tr><th>Search</th><th>Found</th><th>New leads</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($searches as $search)
                        <tr>
                            <td>
                                <div class="table__name">{{ $search->label() }}</div>
                                <div class="caption">Up to {{ number_format($search->max_results) }} · started {{ $search->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="mono">{{ is_null($search->found) ? '—' : number_format($search->found) }}</td>
                            <td class="mono brand-text">{{ is_null($search->added) ? '—' : number_format($search->added) }}</td>
                            <td>
                                @if ($search->status === 'running')
                                    <x-pill tone="signal">Searching</x-pill>
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
                                    @if ($search->status === 'done' && $search->added)
                                        <a href="{{ route('leads', ['search' => $search->id]) }}" style="font-weight: 600">View leads</a>
                                    @endif
                                    @can('edit')
                                    @php $leadCount = $search->businesses_count; @endphp
                                    <form method="post" action="{{ route('searches.destroy', $search) }}"
                                          onsubmit="return confirm('Delete the search {{ addslashes($search->label()) }}{{ $leadCount ? ' and its ' . $leadCount . ' ' . Str::plural('lead', $leadCount) : '' }}? This can’t be undone.')">
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
