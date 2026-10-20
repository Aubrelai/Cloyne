<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Cloyne</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=IBM+Plex+Sans:wght@400;500;600&family=Space+Grotesk:wght@500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/cloyne.css') }}">
</head>
<body>
<main class="auth">
    <div class="auth__brand">Cloyne</div>
    <div class="auth__sub">LaiQ Digital · internal</div>

    @if (session('status'))
        <div class="finding" role="status">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>
</body>
</html>
