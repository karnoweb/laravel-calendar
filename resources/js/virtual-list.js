import { call, onLivewire } from './action-bridge.js';

// Windowed list: the server sends every loaded row as lightweight JSON; only visible rows exist in the DOM.
const OVERSCAN = 6;

function parse(list) {
    try {
        return JSON.parse(list.dataset.rows || '[]');
    } catch {
        return [];
    }
}

function render(list) {
    const host = list.querySelector('[data-kw-rowhost]');
    if (!host) return;
    const rowHeight = Number(list.dataset.rowHeight) || 64;
    const raw = list.dataset.rows || '[]';
    if (list.__kwRaw !== raw || !list.__kwRows) {
        list.__kwRaw = raw;
        list.__kwRows = parse(list);
    }
    const rows = list.__kwRows;
    const start = Math.max(0, Math.floor(list.scrollTop / rowHeight) - OVERSCAN);
    const end = Math.min(rows.length, Math.ceil((list.scrollTop + list.clientHeight) / rowHeight) + OVERSCAN);
    const frag = document.createDocumentFragment();
    for (let i = start; i < end; i++) {
        const row = rows[i];
        const el = document.createElement('div');
        el.setAttribute('role', 'listitem');
        if (row.t === 'day') {
            el.style.top = `${i * rowHeight}px`;
            el.style.height = `${rowHeight}px`;
            el.className = 'kw-list__row kw-list__row--day list-row';
            el.dataset.kwPart = 'list-day-row';
            el.textContent = row.label; // textContent: titles are never interpreted as HTML
        } else {
            const gap = 8;
            el.style.top = `${i * rowHeight + gap / 2}px`;
            el.style.height = `${rowHeight - gap}px`;
            el.className = `kw-list__row kw-list__row--event card card-border list-row ${row.color || ''}`;
            if (row.style) el.setAttribute('style', el.getAttribute('style') + ';' + row.style);
            el.tabIndex = 0;
            el.dataset.ref = row.ref;
            el.dataset.kwPart = 'list-event-row';
            el.dataset.kwAction = 'open-event';
            const time = document.createElement('span');
            time.className = 'kw-list__time';
            time.dataset.kwPart = 'list-event-time';
            time.textContent = row.time;
            const main = document.createElement('span');
            main.className = 'kw-list__main';
            main.dataset.kwPart = 'list-event-main';
            const title = document.createElement('span');
            title.className = 'kw-list__title';
            title.dataset.kwPart = 'list-event-title';
            title.dir = 'auto';
            title.textContent = row.title;
            const source = document.createElement('span');
            source.className = 'kw-list__source badge badge-sm badge-soft';
            source.dataset.kwPart = 'list-event-source';
            source.textContent = row.source;
            main.append(title, source);
            el.append(time, main);
        }
        frag.appendChild(el);
    }
    host.replaceChildren(frag);
}

function bind(list) {
    if (list.__kwBound) {
        list.__kwRows = null;
        render(list);
        return;
    }
    list.__kwBound = true;
    list.addEventListener('scroll', () => requestAnimationFrame(() => render(list)), { passive: true });
    list.addEventListener('click', (e) => {
        const row = e.target.closest('[data-ref]');
        if (row) call(list, 'openEvent', row.dataset.ref);
    });
    list.addEventListener('keydown', (e) => {
        const row = e.target.closest && e.target.closest('[data-ref]');
        if (row && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            call(list, 'openEvent', row.dataset.ref);
        }
    });
    new MutationObserver(() => { list.__kwRows = null; render(list); }).observe(list, { attributes: true, attributeFilter: ['data-rows'] });
    render(list);
}

export function scanLists() {
    document.querySelectorAll('[data-kw-virtual-list]').forEach(bind);
}

export function rerenderLists() {
    document.querySelectorAll('[data-kw-virtual-list]').forEach(render);
}

onLivewire(() => {
    window.Livewire.hook('morphed', scanLists);
    scanLists();
});
document.addEventListener('DOMContentLoaded', scanLists);
