import { call } from './action-bridge.js';

// Keyboard shortcuts while focus is inside a calendar (not in a field or dialog): T today, P/N previous/next.
document.addEventListener('keydown', (e) => {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    const root = e.target.closest && e.target.closest('[data-kw-calendar]');
    if (!root || e.target.closest('input,textarea,select,[contenteditable],[data-kw-modal]')) return;
    if (e.target.closest('[data-kw-event]') && document.querySelector('.kw-event.is-picked')) return;
    const key = e.key.toLowerCase();
    const map = { t: 'today', p: 'previous', n: 'next' };
    if (map[key]) {
        e.preventDefault();
        call(root, map[key]);
    }
});
