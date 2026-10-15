import { call, onLivewire } from './action-bridge.js';

// Optional polling. Skips while the tab is hidden, offline, or a dialog is open (never disturbs editing).
const timers = new WeakMap();

function sync() {
    document.querySelectorAll('[data-kw-calendar]').forEach((root) => {
        const seconds = Number(root.dataset.poll || 0);
        if (!seconds || seconds < 5) {
            if (timers.has(root)) { clearInterval(timers.get(root).id); timers.delete(root); }
            return;
        }
        const existing = timers.get(root);
        if (existing && existing.seconds === seconds) return;
        if (existing) clearInterval(existing.id);
        const id = setInterval(() => {
            if (document.hidden || !navigator.onLine || !document.contains(root)) return;
            if (root.querySelector('[data-kw-modal]') || document.querySelector('.kw-event.is-dragging,.kw-event.is-picked')) return;
            call(root, 'refreshNow');
        }, seconds * 1000);
        timers.set(root, { id, seconds });
    });
}

onLivewire(() => {
    window.Livewire.hook('morphed', sync);
    sync();
});
document.addEventListener('DOMContentLoaded', sync);
