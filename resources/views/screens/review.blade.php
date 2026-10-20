@extends('layouts.app')

@section('title', 'Review queue')

@section('content')
    <div>
        <h1 class="page-title">Review queue</h1>
        <div class="page-sub">Nothing sends until you approve it.</div>
    </div>

    <x-coming-soon title="No drafts yet" step="Built in Step 4: email drafting">
        Once leads are audited, the AI writes a short email for each hot lead, built on that business's biggest gap, and it waits here. You approve, edit or reject each one before anything is sent.
    </x-coming-soon>
@endsection
