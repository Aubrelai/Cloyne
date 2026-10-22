{{-- Messages that slide in at the top right and fade after a few seconds. "Undo" when an action can be reversed. --}}
<div class="toasts" aria-live="polite">
    @if (session('status'))
        <div class="toast" role="status" data-toast>
            <span>{{ session('status') }}</span>
            @if ($undo = session('undo'))
                <form method="post" action="{{ $undo['url'] }}">
                    @csrf
                    <button type="submit" class="toast__action">{{ $undo['label'] ?? 'Undo' }}</button>
                </form>
            @endif
            <button type="button" class="toast__close" aria-label="Dismiss" data-toast-close>×</button>
        </div>
    @endif
</div>
