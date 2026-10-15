import { call, i18n, onLivewire } from './action-bridge.js';
import { announce } from './a11y.js';

function apply() {
    const offline = !navigator.onLine;
    document.querySelectorAll('[data-kw-calendar]').forEach((root) => {
        const bar = root.querySelector('[data-kw-offline]');
        if (bar) bar.hidden = !offline;
        root.dataset.offline = offline ? '1' : '0';
        root.querySelectorAll('form button[type="submit"]').forEach((b) => { b.disabled = offline; });
    });
}

window.addEventListener('offline', apply);
window.addEventListener('online', () => {
    apply();
    // Reconnect: reload authoritative data; never replay queued changes automatically.
    document.querySelectorAll('[data-kw-calendar]').forEach((root) => {
        announce(root, i18n(root).online || '');
        call(root, 'refreshNow');
    });
});
onLivewire(() => {
    window.Livewire.hook('morphed', apply);
    apply();
});
document.addEventListener('DOMContentLoaded', apply);
