@extends('layouts.app')

@section('title', "What's new")

@section('content')
    <h1 class="page-title">What's new</h1>
    <div class="page-sub">Every Cloyne version, newest first.</div>

    <section class="card">
        @foreach ($versions as $v)
            <article @class(['version', 'version--latest' => $loop->first])>
                <div class="version__head">
                    <span class="mono version__number">v{{ $v['version'] }}</span>
                    <strong>{{ $v['title'] }}</strong>
                    @if ($loop->first)<x-pill tone="success">Current</x-pill>@endif
                    <span class="caption version__date">{{ \Illuminate\Support\Carbon::parse($v['date'])->format('F j, Y') }}</span>
                </div>
                <ul class="version__notes">
                    @foreach ($v['notes'] as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </section>
@endsection
