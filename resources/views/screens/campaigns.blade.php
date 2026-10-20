@extends('layouts.app')

@section('title', 'Campaigns')

@section('content')
    <div>
        <h1 class="page-title">Campaigns</h1>
        <div class="page-sub">Email sequences sent from warmed inboxes. Any reply stops the sequence.</div>
    </div>

    <x-coming-soon title="No campaigns yet" step="Built in Step 5: sending">
        A campaign sends approved emails as a 3-step sequence (day 0, 3 and 7) through Instantly, from separate sending domains so LaiQ's main email stays safe. This screen will show what was sent, replies and bookings per campaign, plus the health of each sending inbox.
    </x-coming-soon>
@endsection
