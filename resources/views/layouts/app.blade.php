<!doctype html>
@php
    $theme = auth()->user()?->theme ?? 'dark';
    $titleCount = $pendingDrafts ?? 0;
@endphp
<html lang="en" data-theme="{{ $theme === 'light' ? 'light' : 'dark' }}">
<head>
    @include('partials.head')
    <title>{{ $titleCount > 0 ? "({$titleCount}) " : '' }}@yield('title') · Cloyne</title>
</head>
<body>
<a href="#main" class="skip-link">Skip to content</a>
<div class="app">
    @include('partials.sidebar')

    <main class="main" id="main">
        <header class="topbar">
            @hasSection('search')
                <form action="{{ route('leads') }}" method="get" class="search" role="search">
                    <label for="global-search" class="sr-only">Search businesses</label>
                    <input id="global-search" name="q" type="search" class="input" style="width: 100%" value="{{ request('q') }}" placeholder="Search businesses, phones, emails  ( / )">
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
                        <form method="post" action="{{ route('account.theme') }}" class="theme-switch" aria-label="Theme">
                            @csrf
                            @method('PUT')
                            @foreach (['dark' => 'Dark', 'light' => 'Light', 'system' => 'Match computer'] as $value => $label)
                                <button type="submit" name="theme" value="{{ $value }}" @class(['theme-switch__option', 'is-active' => $theme === $value]) @if ($theme === $value) aria-pressed="true" @endif>{{ $label }}</button>
                            @endforeach
                        </form>
                        <a href="{{ route('account') }}" class="account-menu__item">Account and password</a>
                        <button type="button" class="account-menu__item" data-show-shortcuts>Keyboard shortcuts</button>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="account-menu__item">Sign out</button>
                        </form>
                    </div>
                </details>
            @endauth
        </header>

        {{-- Errors stay on the page until fixed; plain messages pop up and fade. --}}
        @error('audit')
            <div role="alert"><x-pill tone="danger">Audit failed</x-pill> {{ $message }}</div>
        @enderror
        @error('draft')
            <div role="alert"><x-pill tone="danger">Email not written</x-pill> {{ $message }}</div>
        @enderror
        @error('ids')
            <div role="alert"><x-pill tone="danger">Not done</x-pill> {{ $message }}</div>
        @enderror

        @yield('content')
    </main>
</div>

@include('partials.toasts')
@include('partials.dialogs')
@stack('scripts')
</body>
</html>
