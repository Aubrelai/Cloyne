@props(['number', 'copy' => true])
{{-- A phone number you can click to call, shown as (305) 555-0100, with a copy button. --}}
@php
    $display = \App\Support\Phone::display($number);
    $dial = \App\Support\Phone::dial($number);
@endphp
@if ($display)
    <span class="with-copy"><a href="tel:{{ $dial }}" {{ $attributes->class(['mono', 'tel']) }}>{{ $display }}</a>@if ($copy)<x-copy :value="$display" label="phone number" />@endif</span>
@else
    <span class="muted">—</span>
@endif
