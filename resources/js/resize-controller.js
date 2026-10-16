import { rootOf, requestResize, fmtMinute, snap, suppressClick } from './action-bridge.js';

let state = null;

function colAt(x, y, fallback) {
    for (const el of document.elementsFromPoint(x, y)) {
        const col = el.closest && el.closest('[data-kw-timecol]');
        if (col) return col;
    }
    return fallback;
}

function dayAt(x, y) {
    for (const el of document.elementsFromPoint(x, y)) {
        const day = el.closest && el.closest('[data-kw-drop-day]');
        if (day) return day;
    }
    return null;
}

function clearDropTarget() {
    document.querySelectorAll('.is-drop-target').forEach((n) => n.classList.remove('is-drop-target'));
}

document.addEventListener('pointerdown', (e) => {
    const handle = e.target.closest && e.target.closest('[data-kw-resize]');
    if (!handle || e.button > 0) return;
    const card = handle.closest('[data-kw-event][data-resizable="1"]');
    if (!card || !rootOf(card)) return;
    const col = card.closest('[data-kw-timecol]');
    const wrap = card.closest('.kw-week__event');
    const monthGrid = card.closest('.kw-month');
    if (!col && !monthGrid) return;
    e.preventDefault();
    e.stopPropagation();
    state = {
        mode: col ? 'time' : 'date',
        card,
        wrap,
        origHeight: wrap ? wrap.style.height : '',
        startCol: col,
        pointerId: e.pointerId,
        root: rootOf(card),
        target: null,
    };
    state.card.classList.add('is-dragging');
    try { handle.setPointerCapture(e.pointerId); } catch { /* best effort */ }
}, true);

document.addEventListener('pointermove', (e) => {
    if (!state || e.pointerId !== state.pointerId) return;
    e.preventDefault();
    const preview = state.root.querySelector('[data-kw-drag-preview]');
    if (state.mode === 'time') {
        const col = colAt(e.clientX, e.clientY, state.startCol);
        const rect = col.getBoundingClientRect();
        const minute = Math.max(15, snap(((e.clientY - rect.top) / rect.height) * 1440));
        state.target = { iso: col.dataset.kwTimecol, minute };
        const startMin = Number(state.card.dataset.minute || 0);
        if (state.wrap && minute > startMin) {
            state.wrap.style.height = `${Math.max(1.8, ((minute - startMin) / 1440) * 100)}%`;
        }
        if (preview) {
            preview.hidden = false;
            preview.style.left = `${e.clientX + 14}px`;
            preview.style.top = `${e.clientY + 14}px`;
            preview.textContent = `${state.target.iso} → ${fmtMinute(minute)}`;
        }
    } else {
        clearDropTarget();
        const dayEl = dayAt(e.clientX, e.clientY);
        const endMin = Math.max(15, Number(state.card.dataset.endMinute || 60));
        if (dayEl) {
            dayEl.classList.add('is-drop-target');
            state.target = { iso: dayEl.dataset.kwDropDay, minute: endMin };
        }
        if (preview && state.target) {
            preview.hidden = false;
            preview.style.left = `${e.clientX + 14}px`;
            preview.style.top = `${e.clientY + 14}px`;
            preview.textContent = `→ ${state.target.iso} ${fmtMinute(endMin % 1440)}`;
        }
    }
}, { passive: false });

function end(commit) {
    if (!state) return;
    const s = state;
    state = null;
    suppressClick(350);
    clearDropTarget();
    s.card.classList.remove('is-dragging');
    const preview = s.root.querySelector('[data-kw-drag-preview]');
    if (preview) preview.hidden = true;
    if (!commit && s.wrap) {
        s.wrap.style.height = s.origHeight;
    }
    if (commit && s.target) {
        requestResize(s.card, s.card, s.target.iso, s.target.minute);
    }
}

document.addEventListener('pointerup', (e) => state && e.pointerId === state.pointerId && end(true));
document.addEventListener('pointercancel', () => end(false));
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') end(false); });
