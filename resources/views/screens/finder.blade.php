@extends('layouts.app')

@section('title', 'Lead Finder')

@section('content')
    <div>
        <h1 class="page-title">Lead Finder</h1>
        <div class="page-sub">Pick a type of business and an area. Cloyne finds the businesses, verifies emails and audits every site.</div>
    </div>

    <form class="card" method="post" action="#">
        @csrf
        <h2 class="card__title">New search</h2>
        <div class="stats">
            <div class="field">
                <label for="category" class="label">Type of business</label>
                <input id="category" name="category" type="text" class="input" list="category-ideas" value="Dentists" placeholder="e.g. dentists, med spas, roofers">
                <datalist id="category-ideas">
                    <option value="Dentists"><option value="Med spas"><option value="Restaurants"><option value="Gyms"><option value="Law firms">
                    <option value="Real estate agents"><option value="Auto repair"><option value="Salons"><option value="Chiropractors">
                    <option value="Roofers"><option value="Plumbers"><option value="HVAC"><option value="Landscapers">
                </datalist>
            </div>
            <div class="field">
                <label for="package" class="label">Looking for leads for</label>
                <select id="package" name="package" class="select">
                    <option value="">Any package</option>
                    @foreach (\App\Models\Business::packages() as $key => $pkg)
                        <option value="{{ $key }}">{{ $pkg['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="area" class="label">Area</label>
                <input id="area" name="area" type="text" class="input" value="Dallas metro, TX">
            </div>
            <div class="field">
                <label for="max" class="label">Max results</label>
                <select id="max" name="max" class="select"><option>500</option><option>1,000</option><option>5,000</option></select>
            </div>
        </div>
        <fieldset style="margin: 0; padding: 0; border: 0; display: flex; flex-wrap: wrap; gap: 20px;">
            <legend class="label" style="margin-bottom: 8px">Sources</legend>
            <label class="check"><input type="checkbox" name="sources[]" value="maps" checked>Google Maps</label>
            <label class="check"><input type="checkbox" name="sources[]" value="licenses" checked>State license records <span class="caption">(where public)</span></label>
            <label class="check"><input type="checkbox" name="repeat" value="weekly">Repeat every week</label>
        </fieldset>
        <div class="split" style="border-top: 1px solid var(--border); padding-top: 16px; flex-wrap: wrap;">
            <span class="caption">Estimated cost: about $0.50 for 500 places, plus verification and audits</span>
            <button type="submit" class="btn btn--primary">Start search</button>
        </div>
    </form>

    <section class="card card--flush">
        <div class="card__title" style="padding: 16px 16px 8px">Searches</div>
        <div class="table-wrap">
            <table class="table" style="min-width: 760px">
                <thead><tr><th>Search</th><th>Found</th><th>Verified</th><th>Hot 60+</th><th style="width: 260px">Status</th><th>Repeats</th></tr></thead>
                <tbody>
                @foreach ($searches as $s)
                    <tr>
                        <td><div class="table__name">{{ $s['name'] }}</div><div class="caption">{{ $s['meta'] }}</div></td>
                        <td class="mono">{{ $s['found'] }}</td>
                        <td class="mono">{{ $s['verified'] }}</td>
                        <td class="mono brand-text">{{ $s['hot'] }}</td>
                        <td>
                            @if ($s['status'] === 'running')
                                <div class="caption" style="margin-bottom: 4px">{{ $s['step'] }} · {{ $s['progress'] }}%</div>
                                <div class="bar"><div class="bar__fill" style="width: {{ $s['progress'] }}%"></div></div>
                            @elseif ($s['status'] === 'done')
                                <x-pill>Done</x-pill>
                            @else
                                <x-pill>Verification failed</x-pill> <a href="#" style="margin-left: 8px; font-weight: 600">Retry</a>
                            @endif
                        </td>
                        <td @class(['muted' => $s['repeats'] === 'No'])>{{ $s['repeats'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
