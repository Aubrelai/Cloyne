<!doctype html>
<html lang="en" data-theme="dark">
<head>
    @include('partials.head')
    <title>@yield('title') · Cloyne</title>
</head>
<body>
<main class="auth">
    <div class="auth__brand"><img src="{{ asset('favicon.svg') }}" alt="" width="28" height="28" class="auth__mark"> Cloyne</div>
    <div class="auth__sub">LaiQ Digital · internal</div>

    @if (session('status'))
        <div class="finding" role="status">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>
@include('partials.dialogs')
</body>
</html>
