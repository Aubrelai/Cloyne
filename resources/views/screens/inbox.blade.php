@extends('layouts.app')

@section('title', 'Inbox')

@section('content')
    <div>
        <h1 class="page-title">Inbox</h1>
        <div class="page-sub">Replies to your outreach, sorted by the AI.</div>
    </div>

    <x-coming-soon title="No replies yet" step="Built in Step 6: reply agent">
        Replies land here sorted into Interested, Question, Not now and Opt-out. The AI drafts an answer using only your package facts and booking link, and you approve it or take over. Opt-outs are honored automatically.
    </x-coming-soon>
@endsection
