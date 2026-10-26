@extends('layouts.app')

@section('title', 'Campaigns')

@section('content')
    <div class="split" style="align-items: flex-start; flex-wrap: wrap; gap: 12px">
        <div>
            <h1 class="page-title">Campaigns</h1>
            <div class="page-sub">Approved emails go out through Instantly as a 3-email sequence (day 0, 3 and 7) from your warmed sending inboxes. Any reply stops the sequence.</div>
        </div>
        <div class="btn-row" style="align-items: center">
            @if ($on)
                <x-pill tone="success">Sending</x-pill>
            @elseif ($pausedReason)
                <x-pill tone="danger">Paused</x-pill>
            @else
                <x-pill tone="neutral">Off</x-pill>
            @endif
            @can('manage-team')
                @if ($on)
                    <form method="post" action="{{ route('sending.stop') }}" data-confirm="Pause sending? Emails already handed over finish their schedule in Instantly unless you pause it there too." data-confirm-button="Pause sending">
                        @csrf
                        <button type="submit" class="btn btn--secondary">Pause sending</button>
                    </form>
                @else
                    <form method="post" action="{{ route('sending.start') }}" data-confirm="Start sending? Approved emails go out on weekdays, up to {{ number_format($limitToday) }} a day." data-confirm-button="Start sending">
                        @csrf
                        <button type="submit" class="btn btn--primary" @disabled(! $ready) data-busy="Starting…">Start sending</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @error('sending')
        <div role="alert"><x-pill tone="danger">Not started</x-pill> {{ $message }}</div>
    @enderror
    @if ($pausedReason && ! $on)
        <div role="alert" class="list-link"><span><x-pill tone="danger">Paused</x-pill> {{ $pausedReason }}</span></div>
    @endif

    <section class="card">
        <div class="card__head">
            <h2 class="card__title">Before anything sends</h2>
            <span class="caption">{{ collect($readiness)->where('ok', true)->count() }} of {{ count($readiness) }} done</span>
        </div>
        @foreach ($readiness as $item)
            <div class="split connection-row" @if ($loop->last) style="border-bottom: 0" @endif>
                <div>
                    <strong>{{ $item['label'] }}</strong>
                    @if ($item['detail'])<div class="caption">{{ $item['detail'] }}</div>@endif
                </div>
                @if ($item['ok'])<x-pill tone="success">Done</x-pill>@else<x-pill tone="warning">Not yet</x-pill>@endif
            </div>
        @endforeach
    </section>

    <div class="stats">
        <x-stat label="Approved, waiting" :value="number_format($waiting)" :href="route('review', ['status' => 'approved'])" />
        <x-stat label="Left today" :value="number_format($remainingToday)" :delta="'of ' . number_format($limitToday) . ' (' . (int) config('services.instantly.per_inbox_daily') . ' per inbox)'" />
        <x-stat label="Sent, last 7 days" :value="number_format($week['sent'])" />
        <x-stat label="Replies, last 7 days" :value="number_format($week['replied'])" :href="route('inbox')" />
        <x-stat label="Bounced" :value="number_format($health['bounce_rate'] * 100, 1) . '%'" :delta="'pauses above ' . number_format(config('services.instantly.max_bounce_rate') * 100) . '%'" />
    </div>

    @if ($accounts)
        <section class="card">
            <h2 class="card__title">Sending inboxes</h2>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead><tr><th>Inbox</th><th>Sending</th><th>Warm-up</th><th class="num">Warm-up score</th></tr></thead>
                    <tbody>
                        @foreach ($accounts as $a)
                            <tr>
                                <td class="mono">{{ $a['email'] }}</td>
                                <td>@if ($a['sending'])<x-pill tone="success">Ready</x-pill>@else<x-pill tone="warning">Not sending</x-pill>@endif</td>
                                <td>@if ($a['warmup'])<x-pill tone="signal">Warming</x-pill>@else<x-pill tone="neutral">Off</x-pill>@endif</td>
                                <td class="num mono">{{ $a['score'] ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="card">
        <h2 class="card__title">Recent emails</h2>
        @if ($recent->isEmpty())
            <div class="caption">Nothing sent yet. Approved emails show here once sending is on.</div>
        @else
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead><tr><th>Business</th><th>To</th><th>Subject</th><th>Status</th><th>Handed over</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $d)
                            <tr>
                                <td><a href="{{ route('leads', ['lead' => $d->business_id]) }}" class="results-link">{{ $d->business?->displayName() }}</a></td>
                                <td class="mono">{{ $d->to_email }}</td>
                                <td>{{ $d->subject }}</td>
                                <td><x-pill :tone="match ($d->send_status) { 'replied' => 'success', 'bounced', 'unsubscribed' => 'warning', 'queued' => 'signal', default => 'neutral' }">{{ $d->sendStatusLabel() }}</x-pill></td>
                                <td>@if ($d->queued_at)<x-time :at="$d->queued_at" />@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
