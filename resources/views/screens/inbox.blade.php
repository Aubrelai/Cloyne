@extends('layouts.app')

@section('title', 'Inbox')

@section('content')
    <div class="split" style="align-items: flex-start; flex-wrap: wrap; gap: 12px">
        <div>
            <h1 class="page-title">Inbox</h1>
            <div class="page-sub">{{ number_format($unread) }} unread {{ Str::plural('reply', $unread) }}. Replies come back from Instantly every 10 minutes. Opt-outs are honored automatically. Answering with AI comes in version 3.0; for now, reply from Instantly.</div>
        </div>
        <div class="btn-row">
            <a href="{{ route('inbox') }}" @class(['btn', 'btn--sm', 'btn--secondary' => $show === 'people', 'btn--ghost' => $show !== 'people'])>From people</a>
            <a href="{{ route('inbox', ['show' => 'all']) }}" @class(['btn', 'btn--sm', 'btn--secondary' => $show === 'all', 'btn--ghost' => $show !== 'all'])>Include auto-replies</a>
        </div>
    </div>

    <section class="card">
        @forelse ($replies as $r)
            <article @class(['reply', 'reply--unread' => ! $r->read_at])>
                <div class="split" style="align-items: flex-start">
                    <div>
                        <strong>{{ $r->business?->displayName() ?? $r->from_email }}</strong>
                        <span class="caption mono">{{ $r->from_email }}</span>
                        @if ($r->opt_out)<x-pill tone="warning">Opted out</x-pill>@endif
                        @if ($r->is_auto_reply)<x-pill tone="neutral">Auto-reply</x-pill>@endif
                        <div class="caption">{{ $r->subject }} · <x-time :at="$r->received_at" /></div>
                    </div>
                    <div class="btn-row" style="align-items: center">
                        @if ($r->business_id)<a href="{{ route('leads', ['lead' => $r->business_id]) }}" class="btn btn--ghost btn--sm">Open lead</a>@endif
                        @can('edit')
                            <form method="post" action="{{ route('replies.read', $r) }}">
                                @csrf
                                <button type="submit" class="btn btn--ghost btn--sm">{{ $r->read_at ? 'Mark unread' : 'Mark read' }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
                <div class="reply__body">{{ Str::limit($r->body, 1200) }}</div>
            </article>
        @empty
            <div class="caption">
                No replies yet.
                @if (! $sendingOn) Sending is off; turn it on in <a href="{{ route('campaigns') }}" class="results-link">Campaigns</a> once your inboxes are warm. @endif
            </div>
        @endforelse
        @if ($replies->hasPages())
            <div class="btn-row">
                @if ($replies->previousPageUrl())<a href="{{ $replies->previousPageUrl() }}" class="btn btn--secondary btn--sm">Newer</a>@endif
                @if ($replies->nextPageUrl())<a href="{{ $replies->nextPageUrl() }}" class="btn btn--secondary btn--sm">Older</a>@endif
            </div>
        @endif
    </section>
@endsection
