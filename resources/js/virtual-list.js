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
    const rowHeight = Number(list.dataset.rowHeight) || 56;
    const rows = list.__kwRows || (list.__kwRows = parse(list));
    const start = Math.max(0, Math.floor(list.scrollTop / rowHeight) - OVERSCAN);
    const end = Math.min(rows.length, Math.ceil((list.scrollTop + list.clientHeight) / rowHeight) + OVERSCAN);
    const frag = document.createDocumentFragment();
    for (let i = start; i < end; i++) {
        const row = rows[i];
        const el = document.createElement('div');
        el.style.top = `${i * rowHeight}px`;
        el.style.height = `${rowHeight}px`;
        el.setAttribute('role', 'listitem');
        if (row.t === 'day') {
            el.className = 'kw-list__row kw-list__row--day';
            el.textContent = row.label; // textContent: titles are never interpreted as HTML
        } else {
            el.className = `kw-list__row kw-list__row--event ${row.color || ''}`;
            if (row.style) el.setAttribute('style', el.getAttribute('style') + ';' + row.style);
            el.tabIndex = 0;
            el.dataset.ref = row.ref;
            const time = document.createElement('span');
            time.className = 'kw-list__time';
            time.textContent = row.time;
            const title = document.createElement('span');
            title.className = 'kw-list__title';
            title.textContent = row.title;
            const source = document.createElement('span');
            source.className = 'kw-list__source';
            source.textContent = row.source;
            el.append(time, title, source);
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
