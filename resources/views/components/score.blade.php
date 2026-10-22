@props(['value', 'checks' => null, 'hot' => null])
{{-- Lead score badge. Hot (60+ by default) crimson, 40–59 warm (amber), under 40 cold (grey).
     With checks, hovering shows where the points came from. --}}
@php
    $hot ??= (int) config('cloyne.outreach.min_score', 60);
    $tier = $value >= $hot ? 'hot' : ($value >= 40 ? 'warm' : 'cold');
    $why = collect($checks ?? [])
        ->filter(fn ($c) => ($c['points'] ?? 0) > 0)
        ->sortByDesc('points')
        ->map(fn ($c) => $c['title'] . ' +' . $c['points'])
        ->implode("\n");
@endphp
<span {{ $attributes->class(['score', 'score--' . $tier, 'has-tip' => $why !== '']) }} @if ($why !== '') title="Score {{ $value }}&#10;{{ $why }}" tabindex="0" @endif>{{ $value }}</span>
