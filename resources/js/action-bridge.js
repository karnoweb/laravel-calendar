// Bridge between DOM interactions and the Livewire component. The browser only ever sends *intent*
// (reference, target, a fresh operation id); the server re-authorises everything.

// @assets may run after Livewire has already initialised, so handle both orders.
export function onLivewire(fn) {
    if (window.Livewire && typeof window.Livewire.hook === 'function') fn();
    else document.addEventListener('livewire:init', fn, { once: true });
}

export function rootOf(el) {
    return el.closest('[data-kw-calendar]');
}

export function wireOf(el) {
    const host = el.closest('[wire\\:id]');
    if (!host || !window.Livewire) return null;
    const component = window.Livewire.find(host.getAttribute('wire:id'));
    return component ? component.$wire ?? component : null;
}

export function operationId() {
    const bytes = new Uint8Array(16);
    (window.crypto || window.msCrypto).getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
}

export function call(el, method, ...args) {
    const wire = wireOf(el);
    if (!wire) return Promise.resolve(null);
    return wire[method](...args);
}

export function requestMove(el, card, iso, minute) {
    return call(el, 'requestMove', card.dataset.ref, iso, minute, operationId(), card.dataset.version || null);
}

export function requestResize(el, card, iso, endMinute) {
    return call(el, 'requestResize', card.dataset.ref, iso, endMinute, operationId(), card.dataset.version || null);
}

export function i18n(root) {
    try {
        return JSON.parse(root.dataset.i18n || '{}');
    } catch {
        return {};
    }
}

export function addDays(iso, days) {
    const [y, m, d] = iso.split('-').map(Number);
    const date = new Date(Date.UTC(y, m - 1, d + days));
    return date.toISOString().slice(0, 10);
}

export function fmtMinute(minute) {
    const h = String(Math.floor(minute / 60)).padStart(2, '0');
    const m = String(minute % 60).padStart(2, '0');
    return `${h}:${m}`;
}

export const SNAP = 15;
export const snap = (minute) => Math.max(0, Math.min(1439, Math.round(minute / SNAP) * SNAP));
