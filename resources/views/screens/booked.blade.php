@extends('layouts.app')

@section('title', 'Booked')

@section('content')
    @php
        $won = (clone $wonThisMonth)->count();
        $mrr = (float) (clone $wonThisMonth)->sum('deal_value');
    @endphp
    <div>
        <h1 class="page-title">Booked</h1>
        <div class="page-sub">Calls booked by your callers or from email replies. Each one goes to the LaiQ Digital CRM pipeline, and the CRM reports back how the deal goes.</div>
    </div>

    <div class="stats">
        <x-stat label="Booked" :value="number_format($bookings->whereIn('deal_stage', ['booked', null])->where('stage', 'booked')->count())" tone="brand" />
        <x-stat label="Showed up" :value="number_format($bookings->where('deal_stage', 'showed')->count())" />
        <x-stat label="Quoted" :value="number_format($bookings->where('deal_stage', 'quoted')->count())" />
        <x-stat label="Won this month" :value="number_format($won)" :delta="$mrr > 0 ? '$' . number_format($mrr) . '/mo' : null" tone="success" />
    </div>

    @if ($bookings->isEmpty())
        <x-coming-soon title="No booked calls yet" step="Fills up as calls are booked">
            When a caller logs "booked" in the CRM, or someone clicks "Booked a call" on a reply in the Inbox, the lead appears here and goes to the CRM pipeline with its audit, package, emails and calls. Deal stages (showed, quoted, won, lost) come back from the CRM.
        </x-coming-soon>
    @else
        <section class="card card--flush table-wrap">
            <table class="table">
                <thead><tr><th>Business</th><th>Phone</th><th>Best fit</th><th>From</th><th>Deal</th><th>Sold</th><th>Booked</th></tr></thead>
                <tbody>
                @foreach ($bookings as $b)
                    <tr>
                        <td><a href="{{ route('leads', ['lead' => $b->id]) }}" class="table__name" style="color: var(--ink); text-decoration: none">{{ $b->displayName() }}</a><div class="caption">{{ collect([$b->category, $b->location()])->filter()->implode(' · ') }}</div></td>
                        <td><x-phone :number="$b->phone" /></td>
                        <td>{{ $b->packageShort() ?? '—' }}</td>
                        <td class="caption">{{ match ($b->booked_via) { 'call' => 'Call', 'email' => 'Email reply', 'manual' => 'By hand', default => '—' } }}</td>
                        <td>
                            <x-pill :tone="match ($b->deal_stage) { 'won' => 'success', 'lost', 'no_show' => 'warning', 'showed', 'quoted' => 'signal', default => 'neutral' }">{{ $b->dealStageLabel() ?? 'Call booked' }}</x-pill>
                            @if ($b->deal_note)<div class="caption">{{ Str::limit($b->deal_note, 80) }}</div>@endif
                        </td>
                        <td class="mono">
                            @if ($b->deal_package){{ \App\Models\Business::packages()[$b->deal_package]['short'] ?? $b->deal_package }}@endif
                            @if ($b->deal_value) · ${{ number_format($b->deal_value) }}/mo @endif
                            @if (! $b->deal_package && ! $b->deal_value)—@endif
                        </td>
                        <td class="caption">@if ($b->booked_at)<x-time :at="$b->booked_at" />@else<x-time :at="$b->updated_at" />@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
