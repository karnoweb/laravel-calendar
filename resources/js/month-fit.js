import { call, onLivewire } from './action-bridge.js';

/* Fit month cells to the space the host gave us: hide events that do not fit and show a "+N" button instead. */
function fitDay(day, locale) {
    const box = day.querySelector('.kw-day__events');
    if (!box) return;
    box.querySelectorAll('[data-kw-fit]').forEach((e) => e.remove());
    const server = box.querySelector('.kw-day__more');
    if (server) server.hidden = false;
    const evs = [...box.children].filter((c) => !c.classList.contains('kw-day__more'));
    evs.forEach((e) => { e.hidden = false; });
    const dayRect = day.getBoundingClientRect();
    if (dayRect.height < 40) return;
    const limit = dayRect.bottom - 3;
    const over = () => {
        const last = [...box.children].filter((c) => !c.hidden).pop();
        return last && last.getBoundingClientRect().bottom > limit;
    };
    const serverHidden = server ? Number(server.dataset.hidden || 0) : 0;
    let hid = 0;
    let btn = null;
    let guard = evs.length + 2;
    while (over() && guard-- > 0) {
        const vis = evs.filter((e) => !e.hidden);
        if (vis.length <= 1) break;
        vis[vis.length - 1].hidden = true;
        hid++;
        if (!btn) {
            if (server) server.hidden = true;
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'kw-day__more';
            btn.dataset.kwFit = '1';
            btn.addEventListener('click', () => {
                const iso = day.dataset.kwDropDay;
                if (iso) call(day, 'openDay', iso);
            });
            box.appendChild(btn);
        }
        btn.textContent = '+' + (hid + serverHidden).toLocaleString(locale === 'fa' ? 'fa-IR' : 'en');
    }
}

function scrollWeek(root) {
    root.querySelectorAll('[data-kw-scroll]').forEach((el) => {
        if (el.__kwScrolled) return;
        el.__kwScrolled = true;
        const firstEvent = el.querySelector('.kw-week__event');
        const now = el.querySelector('.kw-week__now');
        const hour = parseFloat(getComputedStyle(root).getPropertyValue('--kw-hour')) || 52;
        if (firstEvent) {
            el.scrollTop = Math.max(0, firstEvent.offsetTop - hour);
        } else if (now) {
            el.scrollTop = Math.max(0, now.offsetTop - el.clientHeight / 3);
        } else {
            el.scrollTop = 7 * hour;
        }
        const todayHead = el.querySelector('.kw-week__dayhead.is-today');
        if (todayHead && el.scrollWidth > el.clientWidth + 10 && typeof todayHead.scrollIntoView === 'function') {
            todayHead.scrollIntoView({ block: 'nearest', inline: 'center' });
        }
    });
}

export function fitAll() {
    requestAnimationFrame(() => {
        document.querySelectorAll('[data-kw-calendar]').forEach((root) => {
            if (root.classList.contains('kw-calendar--mini-month') || root.classList.contains('kw-calendar--agenda')) return;
            const locale = root.dataset.locale || 'en';
            root.querySelectorAll('.kw-day').forEach((d) => fitDay(d, locale));
            scrollWeek(root);
        });
    });
}

onLivewire(() => {
    window.Livewire.hook('morphed', fitAll);
    fitAll();
});
document.addEventListener('DOMContentLoaded', fitAll);
