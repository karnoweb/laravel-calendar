import { rootOf, requestMove, fmtMinute, snap, suppressClick } from './action-bridge.js';

const THRESHOLD = 5;
const LONG_PRESS_MS = 320;

let state = null;
let touchBlock = false;

function targetAt(x, y) {
    for (const el of document.elementsFromPoint(x, y)) {
        const col = el.closest && el.closest('[data-kw-timecol]');
        if (col) return { kind: 'time', el: col, iso: col.dataset.kwTimecol };
        const day = el.closest && el.closest('[data-kw-drop-day]');
        if (day) return { kind: 'date', el: day, iso: day.dataset.kwDropDay };
    }
    return null;
}

function clearTarget() {
    document.querySelectorAll('.is-drop-target').forEach((n) => n.classList.remove('is-drop-target'));
    document.querySelectorAll('[data-kw-drop-slot]').forEach((n) => n.remove());
}

function begin(e) {
    state.dragging = true;
    state.card.classList.add('is-dragging');
    const preview = state.root.querySelector('[data-kw-drag-preview]');
    state.preview = preview;
    if (preview) {
        preview.textContent = state.card.querySelector('.kw-event__title')?.textContent || '';
        preview.hidden = false;
    }
    try {
        state.card.setPointerCapture(e.pointerId);
    } catch {
        /* capture is best effort */
    }
}

function resolve(x, y) {
    const t = targetAt(x, y);
    if (!t) return null;
    if (t.kind === 'time' && state.card.dataset.allday !== '1') {
        const rect = t.el.getBoundingClientRect();
        const minute = snap(((y - state.grabY - rect.top) / rect.height) * 1440);
        return { iso: t.iso, minute, el: t.el };
    }
    return { iso: t.iso, minute: null, el: t.el };
}

function renderSlotGhost(drop, title) {
    if (!drop || drop.minute === null || !drop.el) return;
    const ghost = document.createElement('div');
    ghost.className = 'kw-drop-slot';
    ghost.dataset.kwDropSlot = '1';
    ghost.style.top = `${(drop.minute / 1440) * 100}%`;
    ghost.textContent = `${fmtMinute(drop.minute)} — ${title}`;
    drop.el.appendChild(ghost);
}

function onMove(e) {
    if (!state || e.pointerId !== state.pointerId) return;
    if (!state.dragging) {
        const dist = Math.hypot(e.clientX - state.x, e.clientY - state.y);
        if (state.pointerType === 'touch') {
            if (!state.armed) {
                if (dist > THRESHOLD) cancel();
                return;
            }
        } else if (dist < THRESHOLD) return;
        begin(e);
    }
    e.preventDefault();
    const drop = resolve(e.clientX, e.clientY);
    clearTarget();
    state.drop = drop;
    const title = state.card.querySelector('.kw-event__title')?.textContent || '';
    if (drop) {
        drop.el.classList.add('is-drop-target');
        renderSlotGhost(drop, title);
    }
    if (state.preview) {
        state.preview.style.left = `${e.clientX + 14}px`;
        state.preview.style.top = `${e.clientY + 14}px`;
        state.preview.textContent = title + (drop ? ` → ${drop.iso}${drop.minute !== null ? ' ' + fmtMinute(drop.minute) : ''}` : '');
    }
}

function finish(commit) {
    if (!state) return;
    const s = state;
    state = null;
    touchBlock = false;
    clearTimeout(s.timer);
    clearTarget();
    s.card.classList.remove('is-dragging');
    if (s.preview) s.preview.hidden = true;
    if (s.dragging) {
        suppressClick(350);
        if (commit && s.drop) {
            const sameDayNoTime = s.drop.iso === s.card.dataset.day && s.drop.minute === null;
            const sameDayAndTime = s.drop.iso === s.card.dataset.day && s.drop.minute !== null && Number(s.card.dataset.minute) === s.drop.minute;
            if (!sameDayNoTime && !sameDayAndTime) {
                requestMove(s.card, s.card, s.drop.iso, s.drop.minute);
            }
        }
    }
}

function cancel() {
    finish(false);
}

document.addEventListener('pointerdown', (e) => {
    if (e.button > 0) return;
    const card = e.target.closest && e.target.closest('[data-kw-event][data-movable="1"]');
    if (!card || e.target.closest('[data-kw-resize]') || !rootOf(card)) return;
    const rect = card.getBoundingClientRect();
    state = {
        card, root: rootOf(card), pointerId: e.pointerId, pointerType: e.pointerType,
        x: e.clientX, y: e.clientY, grabY: Math.min(24, Math.max(4, e.clientY - rect.top)), dragging: false, armed: e.pointerType !== 'touch', drop: null, preview: null,
    };
    if (e.pointerType === 'touch') {
        touchBlock = false;
        state.timer = setTimeout(() => {
            if (!state) return;
            state.armed = true;
            touchBlock = true;
            if (navigator.vibrate) navigator.vibrate(10);
        }, LONG_PRESS_MS);
    }
});
document.addEventListener('pointermove', onMove, { passive: false });
document.addEventListener('pointerup', (e) => state && e.pointerId === state.pointerId && finish(true));
document.addEventListener('pointercancel', (e) => state && e.pointerId === state.pointerId && !touchBlock && cancel());
document.addEventListener('touchmove', (e) => { if (touchBlock) e.preventDefault(); }, { passive: false });
document.addEventListener('contextmenu', (e) => { if (touchBlock) e.preventDefault(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && state) cancel(); });
