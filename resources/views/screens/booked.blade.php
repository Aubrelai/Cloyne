@extends('layouts.app')

@section('title', 'Booked')

@section('content')
    <div>
        <h1 class="page-title">Booked</h1>
        <div class="page-sub">Every booked call goes to the LaiQ Digital CRM. Deal stages come back here.</div>
    </div>

    <div class="stats">
        @foreach ($funnel as [$label, $value, $tone])
            <x-stat :label="$label" :value="$value" :tone="$tone" />
        @endforeach
    </div>

    <section class="card card--flush table-wrap">
        <table class="table" style="min-width: 820px">
            <thead><tr><th>Business</th><th>Call</th><th>Campaign</th><th>CRM sync</th><th>Deal stage</th></tr></thead>
            <tbody>
            @foreach ($bookings as $b)
                <tr>
                    <td><div class="table__name">{{ $b['business'] }}</div><div class="caption">{{ $b['contact'] }}</div></td>
                    <td>{{ $b['when'] }}</td>
                    <td class="muted">{{ $b['campaign'] }}</td>
                    <td>
                        @if ($b['synced'])
                            <x-pill>In CRM</x-pill> <a href="#" style="margin-left: 6px; font-size: 13px; font-weight: 600">Open</a>
                        @else
                            <x-pill>Sync failed</x-pill> <button type="button" class="btn btn--secondary btn--sm" style="margin-left: 6px">Retry</button>
                        @endif
                    </td>
                    <td @style(['color: var(--success); font-weight: 600' => $b['stage'] === 'Won', 'color: var(--ink-muted)' => $b['stage'] === '—'])>{{ $b['stage'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
