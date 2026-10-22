@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <form class="card" method="post" action="{{ route('login') }}">
        @csrf
        <h1 class="card__title">Sign in</h1>

        @if ($errors->any())
            <div role="alert"><x-pill tone="danger">Not signed in</x-pill> {{ $errors->first() }}</div>
        @endif

        <div class="field">
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" class="input" required autofocus autocomplete="username" value="{{ old('email') }}">
        </div>
        <div class="field">
            <label for="password" class="label">Password</label>
            <div class="input-row">
                <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
                <button type="button" class="btn btn--ghost" data-show-password="password" aria-pressed="false" aria-label="Show password">Show</button>
            </div>
        </div>
        <label class="caption" style="display: flex; gap: 8px; align-items: center">
            <input type="checkbox" name="remember" value="1"> Keep me signed in on this computer
        </label>
        <button type="submit" class="btn btn--primary" data-busy="Signing in…">Sign in</button>
        <div class="caption">Forgot your password? Ask the Owner to reset it in Settings → Team.</div>
    </form>
@endsection
