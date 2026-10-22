@props(['value', 'label' => 'text'])
{{-- Small copy-to-clipboard button. --}}
<button type="button" class="copy-btn" data-copy="{{ $value }}" aria-label="Copy {{ $label }}" title="Copy {{ $label }}"><svg aria-hidden="true" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg></button>
