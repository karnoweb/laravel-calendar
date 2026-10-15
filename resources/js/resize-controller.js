import { rootOf, requestResize, fmtMinute, snap } from './action-bridge.js';

let state = null;

function colAt(x, y, fallback) {
    for (const el of document.elementsFromPoint(x, y)) {
        const col = el.closest && el.closest('[data-kw-timecol]');
        if (col) return col;
    }
    return fallback;
}

document.addEventListener('pointerdown', (e) => {
    const handle = e.target.closest && e.target.closest('[data-kw-resize]');
    if (!handle || e.button > 0) return;
    const card = handle.closest('[data-kw-event]');
    const col = card && card.closest('[data-kw-timecol]');
    if (!card || !col || !rootOf(card)) return;
    e.preventDefault();
    e.stopPropagation();
    state = { card, startCol: col, pointerId: e.pointerId, root: rootOf(card), target: null };
    state.card.classList.add('is-dragging');
    try { handle.setPointerCapture(e.pointerId); } catch { /* best effort */ }
}, true);

document.addEventListener('pointermove', (e) => {
    if (!state || e.pointerId !== state.pointerId) return;
    e.preventDefault();
    const col = colAt(e.clientX, e.clientY, state.startCol);
    const rect = col.getBoundingClientRect();
    const minute = snap(((e.clientY - rect.top) / rect.height) * 1440);
    state.target = { iso: col.dataset.kwTimecol, minute };
    const preview = state.root.querySelector('[data-kw-drag-preview]');
    if (preview) {
        preview.hidden = false;
        preview.style.left = `${e.clientX + 12}px`;
        preview.style.top = `${e.clientY + 12}px`;
        preview.textContent = `${state.target.iso} ${fmtMinute(minute)}`;
    }
}, { passive: false });

function end(commit) {
    if (!state) return;
    const s = state;
    state = null;
    s.card.classList.remove('is-dragging');
    const preview = s.root.querySelector('[data-kw-drag-preview]');
    if (preview) preview.hidden = true;
    if (commit && s.target) requestResize(s.card, s.card, s.target.iso, s.target.minute);
}

document.addEventListener('pointerup', (e) => state && e.pointerId === state.pointerId && end(true));
document.addEventListener('pointercancel', () => end(false));
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') end(false); });
