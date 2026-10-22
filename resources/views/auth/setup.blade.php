@extends('layouts.auth')

@section('title', 'Set up Cloyne')

@section('content')
    <form class="card" method="post" action="{{ route('setup') }}">
        @csrf
        <div>
            <h1 class="card__title">Create the Owner account</h1>
            <div class="caption">This is a one-time step. The Owner can do everything, including adding the team. This page closes once the Owner exists.</div>
        </div>

        @if ($errors->any())
            <div role="alert" style="display: flex; flex-direction: column; gap: 4px">
                @foreach ($errors->all() as $error)
                    <span><x-pill tone="danger">Error</x-pill> {{ $error }}</span>
                @endforeach
            </div>
        @endif

        <div class="field">
            <label for="name" class="label">Your name</label>
            <input id="name" name="name" type="text" class="input" required autofocus maxlength="80" autocomplete="name" value="{{ old('name') }}">
        </div>
        <div class="field">
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" class="input" required autocomplete="username" value="{{ old('email') }}">
        </div>
        <div class="field">
            <label for="password" class="label">Password</label>
            <div class="input-row">
                <input id="password" name="password" type="password" class="input" required minlength="{{ \App\Models\User::MIN_PASSWORD }}" autocomplete="new-password" data-strength="password-hint">
                <button type="button" class="btn btn--ghost" data-show-password="password" aria-pressed="false" aria-label="Show password">Show</button>
            </div>
            <span class="caption" id="password-hint" aria-live="polite">At least {{ \App\Models\User::MIN_PASSWORD }} characters.</span>
        </div>
        <div class="field">
            <label for="password_confirmation" class="label">Password again</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn--primary">Create Owner account</button>
    </form>
@endsection
