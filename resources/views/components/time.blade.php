@props(['at', 'format' => 'relative'])
{{-- "2 hours ago", with the exact date and time (in your time zone) on hover. --}}
@if ($at)
    @php
        $local = $at->copy()->timezone(auth()->user()?->timezone ?: config('app.timezone'));
        $exact = $local->format('D, M j, Y \a\t g:i A T');
    @endphp
    <time datetime="{{ $at->toIso8601String() }}" title="{{ $exact }}" {{ $attributes }}>{{ $format === 'date' ? $local->format('M j, Y') : $at->diffForHumans() }}</time>
@else
    <span class="muted">—</span>
@endif
