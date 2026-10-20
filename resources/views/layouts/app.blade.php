<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Cloyne</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=IBM+Plex+Sans:wght@400;500;600&family=Space+Grotesk:wght@500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/cloyne.css') }}">
</head>
<body>
<div class="app">
    @include('partials.sidebar')

    <main class="main">
        <header class="topbar">
            @hasSection('search')
                <form action="{{ route('leads') }}" method="get" class="search" role="search">
                    <label for="global-search" class="sr-only">Search businesses</label>
                    <input id="global-search" name="q" type="search" class="input" style="width: 100%" value="{{ request('q') }}" placeholder="Search businesses, phones, emails">
                </form>
            @endif
            <div class="topbar__spacer"></div>
            @yield('topbar-actions')
            @auth
                <details class="account-menu">
                    <summary class="avatar" aria-label="Account menu">{{ auth()->user()->initials() }}</summary>
                    <div class="account-menu__panel">
                        <div class="account-menu__who">
                            <strong>{{ auth()->user()->name }}</strong>
                            <div class="caption">{{ auth()->user()->email }}</div>
                            <div style="margin-top: 6px"><x-pill tone="neutral">{{ auth()->user()->roleLabel() }}</x-pill></div>
                        </div>
                        <a href="{{ route('account') }}" class="account-menu__item">Account and password</a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="account-menu__item">Sign out</button>
                        </form>
                    </div>
                </details>
            @endauth
        </header>

        @if (session('status') && ! request()->routeIs('finder'))
            <div class="finding" role="status">{{ session('status') }}</div>
        @endif
        @error('audit')
            <div role="alert"><x-pill tone="danger">Audit failed</x-pill> {{ $message }}</div>
        @enderror
        @error('ids')
            <div role="alert"><x-pill tone="danger">Not deleted</x-pill> {{ $message }}</div>
        @enderror

        @yield('content')
    </main>
</div>
</body>
</html>
