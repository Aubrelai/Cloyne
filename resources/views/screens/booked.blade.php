@extends('layouts.app')

@section('title', 'Booked')

@section('content')
    <div>
        <h1 class="page-title">Booked</h1>
        <div class="page-sub">Leads with a call booked. Sending them to the LaiQ Digital CRM is built in Step 7.</div>
    </div>

    @if ($bookings->isEmpty())
        <x-coming-soon title="No booked calls yet" step="Fills up from Step 6 onward">
            When a lead books a call, it appears here, and Cloyne sends it to the LaiQ Digital CRM with its audit, best-fit package and email thread. Deal stages (showed, quoted, won) come back from the CRM.
        </x-coming-soon>
    @else
        <section class="card card--flush table-wrap">
            <table class="table">
                <thead><tr><th>Business</th><th>Category</th><th>Best fit</th><th>Booked</th></tr></thead>
                <tbody>
                @foreach ($bookings as $b)
                    <tr>
                        <td><a href="{{ route('leads', ['lead' => $b->id]) }}" class="table__name" style="color: var(--ink); text-decoration: none">{{ $b->name }}</a><div class="caption">{{ $b->location() }}</div></td>
                        <td>{{ $b->category }}</td>
                        <td>{{ $b->packageShort() ?? '—' }}</td>
                        <td class="caption">{{ $b->updated_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
