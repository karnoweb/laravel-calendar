import { rootOf, requestMove, addDays, fmtMinute, i18n, SNAP } from './action-bridge.js';
import { announce } from './a11y.js';

// Keyboard alternative to dragging: Space picks up, arrows move, Space drops, Escape cancels, Enter opens.
let held = null;

function isWeekGrid(card) {
    return !!card.closest('[data-kw-timecol]');
}

function describe(h) {
    return h.minute !== null ? `${h.iso} ${fmtMinute(h.minute)}` : h.iso;
}

function drop(card) {
    const h = held;
    held = null;
    card.classList.remove('is-picked');
    const root = rootOf(card);
    announce(root, i18n(root).dropped || '');
    if (h.iso !== h.startIso || h.minute !== h.startMinute) {
        requestMove(card, card, h.iso, h.minute === h.startMinute ? null : h.minute);
    }
}

document.addEventListener('keydown', (e) => {
    const card = e.target.closest && e.target.closest('[data-kw-event]');
    if (!card || !rootOf(card)) return;
    const root = rootOf(card);
    const rtl = getComputedStyle(root).direction === 'rtl';

    if (!held) {
        if (e.key === 'Enter' && e.target === card) {
            e.preventDefault();
            card.click();
        } else if ((e.key === ' ' || e.code === 'Space') && card.dataset.movable === '1' && e.target === card) {
            e.preventDefault();
            const minute = card.dataset.allday === '1' ? null : Number(card.dataset.minute || 0);
            held = { card, iso: card.dataset.day, startIso: card.dataset.day, minute, startMinute: minute };
            card.classList.add('is-picked');
            announce(root, i18n(root).picked || '');
        }
        return;
    }
    if (held.card !== card) return;

    const week = isWeekGrid(card);
    let handled = true;
    switch (e.key) {
        case 'ArrowLeft': held.iso = addDays(held.iso, rtl ? 1 : -1); break;
        case 'ArrowRight': held.iso = addDays(held.iso, rtl ? -1 : 1); break;
        case 'ArrowUp':
            if (week && held.minute !== null) held.minute = Math.max(0, held.minute - SNAP);
            else if (!week) held.iso = addDays(held.iso, -7);
            break;
        case 'ArrowDown':
            if (week && held.minute !== null) held.minute = Math.min(1439, held.minute + SNAP);
            else if (!week) held.iso = addDays(held.iso, 7);
            break;
        case ' ':
        case 'Enter':
            drop(card);
            e.preventDefault();
            return;
        case 'Escape':
            held = null;
            card.classList.remove('is-picked');
            announce(root, i18n(root).cancelled || '');
            e.preventDefault();
            return;
        default: handled = false;
    }
    if (handled) {
        e.preventDefault();
        announce(root, (i18n(root).moved || ':target').replace(':target', describe(held)));
    }
});

// Re-rendering replaces nodes; drop stale state so a new render never inherits a half-finished pick-up.
document.addEventListener('livewire:navigated', () => { held = null; });
