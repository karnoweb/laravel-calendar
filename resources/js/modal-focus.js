// Focus management for dialogs/drawers: move focus in, trap Tab, restore focus on close.
const stack = [];
const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

function panelOf(modal) {
    return modal.querySelector('[role="dialog"],[role="alertdialog"]') || modal;
}

function open(modal) {
    if (modal.__kwOpened) return;
    modal.__kwOpened = true;
    stack.push({ modal, opener: document.activeElement });
    const panel = panelOf(modal);
    const target = modal.querySelector('[data-kw-autofocus]') || panel.querySelector(FOCUSABLE) || panel;
    requestAnimationFrame(() => target.focus({ preventScroll: true }));
}

function close(entry) {
    const i = stack.indexOf(entry);
    if (i >= 0) stack.splice(i, 1);
    const opener = entry.opener;
    if (opener && document.contains(opener)) opener.focus({ preventScroll: true });
}

function scan() {
    document.querySelectorAll('[data-kw-modal]').forEach(open);
    for (const entry of [...stack]) {
        if (!document.contains(entry.modal)) close(entry);
    }
}

new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab' || !stack.length) return;
    const panel = panelOf(stack[stack.length - 1].modal);
    const items = [...panel.querySelectorAll(FOCUSABLE)].filter((n) => n.offsetParent !== null);
    if (!items.length) {
        e.preventDefault();
        return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    } else if (!panel.contains(document.activeElement)) {
        e.preventDefault();
        first.focus();
    }
});

document.addEventListener('DOMContentLoaded', scan);
