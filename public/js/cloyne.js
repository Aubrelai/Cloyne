/* Cloyne: small page behaviours shared by every screen.
   Plain JavaScript, no build step. Each part is switched on by a data- attribute in the views. */
(function () {
    'use strict';

    const $ = (selector, root) => (root || document).querySelector(selector);
    const $$ = (selector, root) => Array.from((root || document).querySelectorAll(selector));
    const typing = (el) => el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));

    /* ---------- Fading messages ---------- */
    function hideToast(toast) {
        toast.classList.add('is-leaving');
        setTimeout(() => toast.remove(), 250);
    }

    function startToast(toast) {
        const wait = toast.querySelector('form') ? 9000 : 5000;
        let timer = setTimeout(() => hideToast(toast), wait);
        toast.addEventListener('mouseenter', () => clearTimeout(timer));
        toast.addEventListener('mouseleave', () => { timer = setTimeout(() => hideToast(toast), 2500); });
    }

    function toast(message) {
        let box = $('.toasts');
        if (!box) {
            box = document.createElement('div');
            box.className = 'toasts';
            box.setAttribute('aria-live', 'polite');
            document.body.appendChild(box);
        }
        const el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'status');
        el.textContent = message;
        box.appendChild(el);
        startToast(el);
    }

    $$('[data-toast]').forEach(startToast);
    document.addEventListener('click', (e) => {
        const close = e.target.closest('[data-toast-close]');
        if (close) hideToast(close.closest('.toast'));
    });

    /* ---------- Styled confirm boxes: <form data-confirm="Delete 3 leads?" data-confirm-button="Delete"> ---------- */
    const dialog = $('#confirm-dialog');

    function askToConfirm(text, buttonLabel, form, submitter) {
        if (!dialog || typeof dialog.showModal !== 'function') {
            return window.confirm(text);
        }
        $('[data-confirm-text]', dialog).textContent = text;
        $('[data-confirm-ok]', dialog).textContent = buttonLabel || 'Confirm';
        dialog.returnValue = '';
        dialog.showModal();
        dialog.addEventListener('close', function onClose() {
            dialog.removeEventListener('close', onClose);
            if (dialog.returnValue === 'ok') {
                form.dataset.confirmed = '1';
                form.requestSubmit(submitter || undefined);
            }
        });

        return false;
    }

    /* ---------- One submit at a time: buttons lock and can say what they're doing (data-busy="Auditing…") ---------- */
    document.addEventListener('submit', (e) => {
        const form = e.target;
        const submitter = e.submitter;

        // The question lives on the button that was clicked, or on the form.
        const asker = (submitter && submitter.dataset.confirm) ? submitter : form;
        if (asker.dataset.confirm && form.dataset.confirmed !== '1') {
            const skip = asker.dataset.confirmIf && !$(asker.dataset.confirmIf);
            if (!skip && askToConfirm(asker.dataset.confirm, asker.dataset.confirmButton, form, submitter) === false) {
                e.preventDefault();
                return;
            }
        }
        delete form.dataset.confirmed;

        if ((form.method || 'get').toLowerCase() !== 'post' || form.hasAttribute('data-no-lock')) return;

        form.dataset.submitting = '1';
        const buttons = $$('button[type="submit"], button:not([type])', form)
            .concat(form.id ? $$('button[form="' + form.id + '"]') : []);
        setTimeout(() => {
            buttons.forEach((b) => { b.disabled = true; });
            if (submitter && submitter.dataset.busy) {
                submitter.classList.add('is-busy');
                submitter.textContent = submitter.dataset.busy;
            }
        }, 0);
    }, true);

    // Back button after a submit: unlock buttons the browser restored from its cache.
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) $$('button[disabled]').forEach((b) => { if (!b.hasAttribute('data-keep-disabled')) b.disabled = false; });
    });

    /* ---------- Copy buttons: <button data-copy="text"> ---------- */
    document.addEventListener('click', async (e) => {
        const button = e.target.closest('[data-copy]');
        if (!button) return;
        e.preventDefault();
        const text = button.dataset.copy;
        try {
            await navigator.clipboard.writeText(text);
        } catch (err) {
            const area = document.createElement('textarea');
            area.value = text;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }
        button.classList.add('is-copied');
        setTimeout(() => button.classList.remove('is-copied'), 1200);
        toast('Copied ' + (button.getAttribute('aria-label') || '').replace(/^Copy /, '') + '.');
    });

    /* ---------- Show password: <button data-show-password="input-id"> ---------- */
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-show-password]');
        if (!button) return;
        const input = document.getElementById(button.dataset.showPassword);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
        button.setAttribute('aria-label', (show ? 'Hide' : 'Show') + ' password');
    });

    /* ---------- Password strength: <input data-strength="hint-id"> ---------- */
    $$('[data-strength]').forEach((input) => {
        const hint = document.getElementById(input.dataset.strength);
        if (!hint) return;
        const update = () => {
            const v = input.value;
            let points = 0;
            if (v.length >= 10) points++;
            if (v.length >= 14) points++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) points++;
            if (/\d/.test(v)) points++;
            if (/[^A-Za-z0-9]/.test(v)) points++;
            const word = !v ? '' : (v.length < 10 ? 'Too short' : (points >= 4 ? 'Strong' : (points >= 3 ? 'OK' : 'Weak')));
            hint.textContent = word ? 'Password: ' + word : 'At least 10 characters.';
            hint.dataset.level = word.toLowerCase().replace(' ', '-');
        };
        input.addEventListener('input', update);
        update();
    });

    /* ---------- Live word count: <textarea data-word-count="counter-id" data-word-limit="120"> ---------- */
    $$('[data-word-count]').forEach((area) => {
        const counter = document.getElementById(area.dataset.wordCount);
        const limit = parseInt(area.dataset.wordLimit || '0', 10);
        if (!counter) return;
        const update = () => {
            const words = (area.value.trim().match(/\S+/g) || []).length;
            counter.textContent = words + (limit ? ' / ' + limit : '') + ' words';
            counter.classList.toggle('is-over', limit > 0 && words > limit);
        };
        area.addEventListener('input', update);
        update();
    });

    /* ---------- Unsaved edits: <form data-warn-unsaved> ---------- */
    $$('form[data-warn-unsaved]').forEach((form) => {
        form.addEventListener('input', () => { form.dataset.dirty = '1'; });
    });
    window.addEventListener('beforeunload', (e) => {
        const dirty = $$('form[data-warn-unsaved][data-dirty="1"]').some((f) => f.dataset.submitting !== '1');
        const leavingBySubmit = $$('form[data-submitting="1"]').length > 0;
        if (dirty && !leavingBySubmit) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    /* ---------- Toggle a class: <button data-toggle-class="is-phone" data-toggle-target="#preview"> ---------- */
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-toggle-class]');
        if (!button) return;
        const target = $(button.dataset.toggleTarget);
        if (!target) return;
        const on = target.classList.toggle(button.dataset.toggleClass);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
    });

    /* ---------- Lead Finder cost estimate: <select data-cost-per="0.004" data-cost-target="id"> ---------- */
    $$('[data-cost-per]').forEach((select) => {
        const target = document.getElementById(select.dataset.costTarget);
        const per = parseFloat(select.dataset.costPer);
        if (!target || !per) return;
        const update = () => {
            const n = parseInt(select.value, 10) || 0;
            const dollars = n * per;
            target.textContent = 'Up to ' + n.toLocaleString() + ' businesses · about $' + (dollars < 10 ? dollars.toFixed(2) : Math.round(dollars));
        };
        select.addEventListener('change', update);
        update();
    });

    /* ---------- Keyboard shortcuts ---------- */
    const shortcuts = $('#shortcuts-dialog');
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-show-shortcuts]') && shortcuts && shortcuts.showModal) {
            const menu = e.target.closest('details');
            if (menu) menu.open = false;
            shortcuts.showModal();
        }
    });

    function move(list, step) {
        const items = $$('[data-key-item]', list);
        if (!items.length) return;
        let index = items.findIndex((el) => el.classList.contains('is-selected'));
        index = index === -1 ? (step > 0 ? 0 : items.length - 1) : Math.min(items.length - 1, Math.max(0, index + step));
        const link = items[index].matches('a') ? items[index] : $('a', items[index]);
        if (link && !items[index].classList.contains('is-selected')) link.click();
    }

    document.addEventListener('keydown', (e) => {
        if (e.metaKey || e.ctrlKey || e.altKey || typing(document.activeElement)) return;
        if ($('dialog[open]')) return;
        const key = e.key;

        if (key === '/') {
            const search = $('#global-search');
            e.preventDefault();
            if (search) { search.focus(); search.select(); } else { window.location.href = '/leads?focus=search'; }
            return;
        }
        if (key === '?' && shortcuts && shortcuts.showModal) {
            e.preventDefault();
            shortcuts.showModal();
            return;
        }

        // Lists: <div data-key-list data-next="j ArrowDown" data-prev="k ArrowUp"> with [data-key-item] children.
        const list = $('[data-key-list]');
        if (list) {
            if ((list.dataset.next || '').split(' ').includes(key)) { e.preventDefault(); move(list, 1); return; }
            if ((list.dataset.prev || '').split(' ').includes(key)) { e.preventDefault(); move(list, -1); return; }
        }

        // Single keys: <button data-key="a">, <textarea data-key-focus="e">.
        const lower = key.length === 1 ? key.toLowerCase() : key;
        const target = $('[data-key="' + lower + '"]:not([disabled])');
        if (target) { e.preventDefault(); target.click(); return; }
        const field = $('[data-key-focus="' + lower + '"]');
        if (field) { e.preventDefault(); field.focus(); }
    });

    if (new URLSearchParams(window.location.search).get('focus') === 'search') {
        const search = $('#global-search');
        if (search) search.focus();
    }
})();
