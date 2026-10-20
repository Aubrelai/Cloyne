@props(['value'])
{{-- Lead score badge. 60+ hot (crimson), 40-59 warm (amber), under 40 cold (grey). --}}
@php
    $tier = $value >= 60 ? 'hot' : ($value >= 40 ? 'warm' : 'cold');
@endphp
<span {{ $attributes->class(['score', 'score--' . $tier]) }}>{{ $value }}</span>
