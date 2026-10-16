import { call, onLivewire } from './action-bridge.js';

function initRovingTabindex(root) {
    // Month view: rove across .kw-day__num buttons
    const dayBtns = Array.from(root.querySelectorAll('.kw-day__num'));
    if (dayBtns.length > 0) {
        let active = dayBtns.find((b) => b.closest('.kw-day.is-selected'))
            || dayBtns.find((b) => b.closest('.kw-day.is-today'))
            || dayBtns.find((b) => !b.closest('.kw-day.is-outside'))
            || dayBtns[0];
        dayBtns.forEach((b) => {
            b.tabIndex = b === active ? 0 : -1;
        });
    }

    // Week/Day view: rove across .kw-week__date buttons
    const weekBtns = Array.from(root.querySelectorAll('.kw-week__date'));
    if (weekBtns.length > 0) {
        let active = weekBtns.find((b) => b.closest('.kw-week__dayhead.is-today')) || weekBtns[0];
        weekBtns.forEach((b) => {
            b.tabIndex = b === active ? 0 : -1;
        });
    }
}

export function scanRovingGrids() {
    document.querySelectorAll('[data-kw-calendar]').forEach(initRovingTabindex);
}

// Keyboard shortcuts & roving grid navigation
document.addEventListener('keydown', (e) => {
    const root = e.target.closest && e.target.closest('[data-kw-calendar]');
    if (!root) return;

    // Roving tabindex arrow-key navigation inside Month or Week day buttons
    const dayBtn = e.target.closest && e.target.closest('.kw-day__num, .kw-week__date');
    if (dayBtn && !e.ctrlKey && !e.metaKey && !e.altKey) {
        const isMonth = dayBtn.classList.contains('kw-day__num');
        const list = Array.from(root.querySelectorAll(isMonth ? '.kw-day__num' : '.kw-week__date'));
        const idx = list.indexOf(dayBtn);
        if (idx !== -1) {
            const rtl = getComputedStyle(root).direction === 'rtl';
            const stepForward = rtl ? -1 : 1;
            let nextIdx = null;
            switch (e.key) {
                case 'ArrowRight': nextIdx = idx + stepForward; break;
                case 'ArrowLeft': nextIdx = idx - stepForward; break;
                case 'ArrowDown': nextIdx = isMonth ? idx + 7 : idx + 1; break;
                case 'ArrowUp': nextIdx = isMonth ? idx - 7 : idx - 1; break;
                case 'Home': nextIdx = isMonth ? idx - (idx % 7) : 0; break;
                case 'End': nextIdx = isMonth ? idx + (6 - (idx % 7)) : list.length - 1; break;
                default: break;
            }
            if (nextIdx !== null) {
                e.preventDefault();
                const clamped = Math.max(0, Math.min(list.length - 1, nextIdx));
                list.forEach((b, i) => { b.tabIndex = i === clamped ? 0 : -1; });
                list[clamped].focus();
                return;
            }
        }
    }

    if (e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.target.closest('input,textarea,select,[contenteditable],[data-kw-modal],[data-kw-part="datepicker-popover"],[data-kw-part="timepicker-popover"]')) return;
    if (e.target.closest('[data-kw-event]') && document.querySelector('.kw-event.is-picked')) return;
    const key = e.key.toLowerCase();
    const map = { t: 'today', p: 'previous', n: 'next' };
    if (map[key]) {
        e.preventDefault();
        call(root, map[key]);
    }
});

onLivewire(() => {
    window.Livewire.hook('morphed', scanRovingGrids);
    scanRovingGrids();
});
document.addEventListener('DOMContentLoaded', scanRovingGrids);
