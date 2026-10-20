@props(['tone' => null])
{{-- Status pill: always a word, never color alone.
     tone: success | warning | danger | signal | neutral. Left empty, it's picked from the word. --}}
@php
    $word = trim($slot);
    $tone ??= match ($word) {
        'Verified', 'In CRM', 'Active', 'Healthy', 'Done', 'Connected', 'Interested' => 'success',
        'Risky', 'Warming', 'Not now' => 'warning',
        'Bounced', 'Invalid', 'Sync failed', 'Opt-out', 'Failed', 'Verification failed' => 'danger',
        'AI drafted', 'AI suggested reply', 'Auditing', 'Searching' => 'signal',
        'Unverified' => 'neutral',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->class(['pill', 'pill--' . $tone]) }}>{{ $slot }}</span>
