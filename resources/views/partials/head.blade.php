{{-- Shared <head> for every page: theme, fonts, styles, icon, script. --}}
@php
    $theme = auth()->user()?->theme ?? 'dark';
@endphp
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark light">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=IBM+Plex+Sans:wght@400;500;600&family=Space+Grotesk:wght@500;600&display=swap">
<link rel="stylesheet" href="{{ asset('css/cloyne.css') }}?v=4">
@if ($theme === 'system')
    <script>document.documentElement.dataset.theme = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';</script>
@endif
<script src="{{ asset('js/cloyne.js') }}?v=4" defer></script>
