@props(['title', 'step'])
{{-- Empty state for a screen whose feature isn't built yet. --}}
<section class="card" style="align-items: flex-start; gap: 12px; padding: 32px 24px">
    <x-pill tone="neutral">{{ $step }}</x-pill>
    <h2 class="card__title">{{ $title }}</h2>
    <div class="muted" style="max-width: 620px">{{ $slot }}</div>
</section>
