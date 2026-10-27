@extends('layouts.app')

@section('title', 'Inbox')

@section('content')
    <div class="split" style="align-items: flex-start; flex-wrap: wrap; gap: 12px">
        <div>
            <h1 class="page-title">Inbox</h1>
            <div class="page-sub">{{ number_format($unread) }} unread {{ Str::plural('reply', $unread) }}. Replies come back from Instantly every 10 minutes; opt-outs are honored automatically. With AI answers on, each reply is sorted and an answer is drafted from the <a href="{{ route('settings') }}#factsheet" class="results-link">fact sheet</a>. Nothing goes until someone approves it.</div>
        </div>
        <div class="btn-row" style="align-items: center">
            @if ($agentOn)<x-pill tone="signal">AI answers on</x-pill>@else<x-pill tone="neutral">AI answers off</x-pill>@endif
            @can('manage-team')
                <form method="post" action="{{ route('reply-agent') }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="on" value="{{ $agentOn ? 0 : 1 }}">
                    <button type="submit" class="btn btn--ghost btn--sm">{{ $agentOn ? 'Turn off' : 'Turn on' }}</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="btn-row" style="justify-content: flex-start">
        <a href="{{ route('inbox', ['show' => 'todo']) }}" @class(['btn', 'btn--sm', 'btn--secondary' => $show === 'todo', 'btn--ghost' => $show !== 'todo'])>To answer</a>
        <a href="{{ route('inbox') }}" @class(['btn', 'btn--sm', 'btn--secondary' => $show === 'people', 'btn--ghost' => $show !== 'people'])>From people</a>
        <a href="{{ route('inbox', ['show' => 'all']) }}" @class(['btn', 'btn--sm', 'btn--secondary' => $show === 'all', 'btn--ghost' => $show !== 'all'])>Include auto-replies</a>
    </div>

    @error('reply')
        <div role="alert"><x-pill tone="danger">Not done</x-pill> {{ $message }}</div>
    @enderror

    <section class="card">
        @forelse ($replies as $r)
            <article @class(['reply', 'reply--unread' => ! $r->read_at])>
                <div class="split" style="align-items: flex-start">
                    <div>
                        <strong>{{ $r->business?->displayName() ?? $r->from_email }}</strong>
                        <span class="caption mono">{{ $r->from_email }}</span>
                        @if ($r->category)<x-pill :tone="match ($r->category) { 'interested' => 'success', 'opt_out' => 'warning', 'question' => 'signal', default => 'neutral' }">{{ $r->categoryLabel() }}</x-pill>@elseif ($r->opt_out)<x-pill tone="warning">Opted out</x-pill>@endif
                        @if ($r->is_auto_reply)<x-pill tone="neutral">Auto-reply</x-pill>@endif
                        @if ($r->business?->stage === 'booked')<x-pill tone="success">Booked</x-pill>@endif
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

                @unless ($r->is_auto_reply || $r->opt_out)
                    <div class="reply__answer">
                        @if ($r->answer_status === 'sent')
                            <div class="caption"><x-pill tone="success">Answered</x-pill> by {{ $r->answeredBy?->name ?? 'the team' }} · <x-time :at="$r->answered_at" /></div>
                            <div class="reply__sent">{{ $r->answer }}</div>
                        @elseif ($r->answer_status === 'handled')
                            <div class="caption"><x-pill tone="neutral">Handled by a person</x-pill> <x-time :at="$r->answered_at" /></div>
                        @elseif ($r->needs_person)
                            <div class="caption"><x-pill tone="warning">Needs you</x-pill> {{ $r->handoff_reason }}</div>
                        @elseif ($r->answer_status === 'failed')
                            <div class="caption"><x-pill tone="danger">Not drafted</x-pill> {{ $r->answer_error }}</div>
                        @endif

                        @can('edit')
                            @if (in_array($r->answer_status, [null, 'drafted', 'failed'], true))
                                @if ($r->answer_status === 'drafted' || $r->needs_person)
                                    <form method="post" action="{{ route('replies.send', $r) }}" class="reply__form" data-warn-unsaved>
                                        @csrf
                                        <label for="answer-{{ $r->id }}" class="label">{{ $r->answer ? 'AI answer, from the fact sheet' : 'Your answer' }}</label>
                                        <textarea id="answer-{{ $r->id }}" name="answer" class="textarea" rows="4" maxlength="3000" required>{{ old('answer', $r->answer) }}</textarea>
                                        @if ($r->answer_error)<div class="caption">{{ $r->answer_error }}</div>@endif
                                        <div class="btn-row" style="justify-content: flex-start">
                                            <button type="submit" class="btn btn--primary btn--sm" @disabled(! $canSend) data-busy="Sending…" data-confirm="Send this answer to {{ $r->from_email }}?" data-confirm-button="Send">Approve and send</button>
                                            <button type="submit" form="handled-{{ $r->id }}" class="btn btn--ghost btn--sm">I'll handle it</button>
                                            @if ($r->business && $r->business->stage !== 'booked')
                                                <button type="submit" form="booked-{{ $r->id }}" class="btn btn--ghost btn--sm">Booked a call</button>
                                            @endif
                                        </div>
                                    </form>
                                @else
                                    <div class="btn-row" style="justify-content: flex-start">
                                        <form method="post" action="{{ route('replies.draft', $r) }}">
                                            @csrf
                                            <button type="submit" class="btn btn--secondary btn--sm" data-busy="Drafting…">Draft an answer</button>
                                        </form>
                                        <button type="submit" form="handled-{{ $r->id }}" class="btn btn--ghost btn--sm">I'll handle it</button>
                                        @if ($r->business && $r->business->stage !== 'booked')
                                            <button type="submit" form="booked-{{ $r->id }}" class="btn btn--ghost btn--sm">Booked a call</button>
                                        @endif
                                    </div>
                                @endif
                                <form method="post" action="{{ route('replies.handled', $r) }}" id="handled-{{ $r->id }}">@csrf</form>
                                @if ($r->business)
                                    <form method="post" action="{{ route('replies.booked', $r) }}" id="booked-{{ $r->id }}">@csrf</form>
                                @endif
                            @endif
                        @endcan
                    </div>
                @endunless
            </article>
        @empty
            <div class="caption">
                No replies {{ $show === 'todo' ? 'to answer' : 'yet' }}.
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
