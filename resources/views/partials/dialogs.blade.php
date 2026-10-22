{{-- Cloyne-styled confirm box, used by any form with data-confirm="…". --}}
<dialog class="dialog" id="confirm-dialog" aria-labelledby="confirm-title">
    <form method="dialog" class="dialog__body">
        <h2 class="card__title" id="confirm-title">Are you sure?</h2>
        <p class="dialog__text" data-confirm-text></p>
        <div class="btn-row">
            <button type="submit" value="cancel" class="btn btn--ghost" autofocus>Cancel</button>
            <button type="submit" value="ok" class="btn btn--danger" data-confirm-ok>Confirm</button>
        </div>
    </form>
</dialog>

{{-- Keyboard shortcuts, opened with "?" or from the account menu. --}}
<dialog class="dialog" id="shortcuts-dialog" aria-labelledby="shortcuts-title">
    <form method="dialog" class="dialog__body">
        <h2 class="card__title" id="shortcuts-title">Keyboard shortcuts</h2>
        <dl class="facts shortcuts">
            <dt><kbd>/</kbd></dt><dd>Search businesses</dd>
            <dt><kbd>?</kbd></dt><dd>Show this list</dd>
            <dt><kbd>↑</kbd> <kbd>↓</kbd></dt><dd>Leads: previous or next lead</dd>
            <dt><kbd>J</kbd> <kbd>K</kbd></dt><dd>Review queue: next or previous email</dd>
            <dt><kbd>A</kbd></dt><dd>Review queue: approve</dd>
            <dt><kbd>S</kbd></dt><dd>Review queue: skip lead</dd>
            <dt><kbd>R</kbd></dt><dd>Review queue: rewrite</dd>
            <dt><kbd>E</kbd></dt><dd>Review queue: edit the email</dd>
        </dl>
        <div class="btn-row"><button type="submit" class="btn btn--secondary">Close</button></div>
    </form>
</dialog>
