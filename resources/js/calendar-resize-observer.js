import { rerenderLists } from './virtual-list.js';
import { fitAll } from './month-fit.js';
import { onLivewire, rootOf, call } from './action-bridge.js';

function applySize(root) {
    const w = root.getBoundingClientRect().width;
    if (w > 0) {
        root.dataset.size = w < 620 ? 'sm' : w < 920 ? 'md' : 'lg';
    }
    if (root.__kwDrawerOpen) {
        root.dataset.drawerOpen = '1';
        const side = root.querySelector('.kw-side');
        if (side) side.hidden = false;
    }
}

function isDrawerMode(root) {
    return root.dataset.size === 'sm' || root.dataset.size === 'md' || window.innerWidth <= 920;
}

function setDrawer(root, open) {
    root.__kwDrawerOpen = open;
    if (open) {
        root.dataset.drawerOpen = '1';
        const side = root.querySelector('.kw-side');
        if (side) side.hidden = false;
    } else {
        delete root.dataset.drawerOpen;
    }
    const btn = root.querySelector('[data-kw-side-toggle]');
    if (btn) {
        btn.classList.toggle('is-active', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
}

if (typeof document !== 'undefined') {
    document.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest && e.target.closest('[data-kw-side-toggle]');
        if (toggleBtn) {
            const root = rootOf(toggleBtn);
            if (root && isDrawerMode(root)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                setDrawer(root, root.dataset.drawerOpen !== '1');
                return;
            }
        }
        const autoCloseEl = e.target.closest && e.target.closest('[data-kw-side-autoclose]');
        if (autoCloseEl) {
            const root = rootOf(autoCloseEl);
            if (root && isDrawerMode(root)) {
                setDrawer(root, false);
            }
        }
        const closeEl = e.target.closest && e.target.closest('[data-kw-side-close]');
        if (closeEl) {
            const root = rootOf(closeEl);
            if (!root) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            if (isDrawerMode(root)) {
                setDrawer(root, false);
            } else {
                call(closeEl, 'toggleSidePanel');
            }
        }
    }, true);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('[data-kw-calendar][data-drawer-open="1"]').forEach((root) => {
                setDrawer(root, false);
            });
        }
    });
}

const ro = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver((entries) => {
    for (const entry of entries) {
        applySize(entry.target);
    }
    rerenderLists();
    fitAll();
});

function scan() {
    document.querySelectorAll('[data-kw-calendar]').forEach((root) => {
        applySize(root);
        if (ro && !root.__kwObserved) {
            root.__kwObserved = true;
            ro.observe(root);
        }
    });
    rerenderLists();
    fitAll();
}

onLivewire(() => {
    window.Livewire.hook('morphed', scan);
    scan();
});
document.addEventListener('DOMContentLoaded', scan);
