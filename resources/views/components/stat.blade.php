@props(['label', 'value', 'delta' => null, 'trend' => null, 'tone' => null])
{{-- A stat card: one number, its label and an optional change line. tone: brand | success --}}
<div class="stat">
    <div class="label">{{ $label }}</div>
    <div @class(['stat__value', 'stat__value--brand' => $tone === 'brand', 'stat__value--success' => $tone === 'success'])>{{ $value }}</div>
    @if ($delta)
        <div @class(['stat__delta', 'stat__delta--up' => $trend === 'up', 'stat__delta--down' => $trend === 'down'])>{{ $delta }}</div>
    @endif
</div>
