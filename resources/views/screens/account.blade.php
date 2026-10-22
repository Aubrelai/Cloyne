@extends('layouts.app')

@section('title', 'Account')

@section('content')
    <div>
        <h1 class="page-title">Account</h1>
        <div class="page-sub">{{ $user->email }} · {{ $user->roleLabel() }}</div>
    </div>

    @if ($user->must_change_password)
        <section class="card" style="border-color: var(--warning)">
            <div class="split"><h2 class="card__title">Choose your own password</h2><x-pill tone="warning">Required</x-pill></div>
            <div class="caption">You signed in with a temporary password. Pick a new one to start using Cloyne.</div>
        </section>
    @endif

    <form class="card" method="post" action="{{ route('account.password') }}">
        @csrf
        @method('PUT')
        <h2 class="card__title">Change password</h2>

        @if ($errors->hasAny(['current_password', 'password']))
            <div role="alert" style="display: flex; flex-direction: column; gap: 4px">
                @foreach ($errors->all() as $error)
                    <span><x-pill tone="danger">Not changed</x-pill> {{ $error }}</span>
                @endforeach
            </div>
        @endif

        <div class="stats">
            <div class="field">
                <label for="current_password" class="label">{{ $user->must_change_password ? 'Temporary password' : 'Current password' }}</label>
                <div class="input-row">
                    <input id="current_password" name="current_password" type="password" class="input" required autocomplete="current-password">
                    <button type="button" class="btn btn--ghost" data-show-password="current_password" aria-pressed="false" aria-label="Show password">Show</button>
                </div>
            </div>
            <div class="field">
                <label for="password" class="label">New password</label>
                <div class="input-row">
                    <input id="password" name="password" type="password" class="input" required minlength="{{ \App\Models\User::MIN_PASSWORD }}" autocomplete="new-password" data-strength="password-hint">
                    <button type="button" class="btn btn--ghost" data-show-password="password" aria-pressed="false" aria-label="Show new password">Show</button>
                </div>
            </div>
            <div class="field">
                <label for="password_confirmation" class="label">New password again</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
            </div>
        </div>
        <div class="split" style="border-top: 1px solid var(--border); padding-top: 16px; flex-wrap: wrap">
            <span class="caption" id="password-hint" aria-live="polite">At least {{ \App\Models\User::MIN_PASSWORD }} characters.</span>
            <button type="submit" class="btn btn--primary">Change password</button>
        </div>
    </form>

    @unless ($user->must_change_password)
        <form class="card" method="post" action="{{ route('account.preferences') }}">
            @csrf
            @method('PUT')
            <h2 class="card__title">Preferences</h2>
            @if ($errors->hasAny(['timezone', 'theme']))
                <div role="alert"><x-pill tone="danger">Not saved</x-pill> {{ $errors->first('timezone') ?: $errors->first('theme') }}</div>
            @endif
            <div class="stats">
                <div class="field">
                    <label for="theme" class="label">Theme</label>
                    <select id="theme" name="theme" class="select">
                        @foreach (['dark' => 'Dark', 'light' => 'Light', 'system' => 'Match my computer'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('theme', $user->theme) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="timezone" class="label">Time zone</label>
                    <select id="timezone" name="timezone" class="select">
                        @foreach (\App\Models\User::TIMEZONES as $value => $label)
                            <option value="{{ $value }}" @selected(old('timezone', $user->timezone) === $value)>{{ $label }}</option>
                        @endforeach
                        @unless (array_key_exists($user->timezone, \App\Models\User::TIMEZONES))
                            <option value="{{ $user->timezone }}" selected>{{ $user->timezone }}</option>
                        @endunless
                    </select>
                </div>
            </div>
            <div class="split" style="flex-wrap: wrap">
                <span class="caption">Dates and times in Cloyne show in your time zone. Now: {{ now($user->timezone)->format('g:i A T') }}</span>
                <button type="submit" class="btn btn--secondary">Save preferences</button>
            </div>
        </form>

        <form class="card" method="post" action="{{ route('account.name') }}">
            @csrf
            @method('PUT')
            <h2 class="card__title">Name</h2>
            @error('name')
                <div role="alert"><x-pill tone="danger">Not saved</x-pill> {{ $message }}</div>
            @enderror
            <div class="split" style="gap: 12px; flex-wrap: wrap; align-items: flex-end">
                <div class="field" style="flex: 1 1 240px">
                    <label for="name" class="label">Your name</label>
                    <input id="name" name="name" type="text" class="input" required maxlength="80" value="{{ old('name', $user->name) }}">
                </div>
                <button type="submit" class="btn btn--secondary">Save name</button>
            </div>
        </form>
    @endunless
@endsection
