import { rerenderLists } from './virtual-list.js';
import { fitAll } from './month-fit.js';
import { onLivewire } from './action-bridge.js';

const ro = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver((entries) => {
    for (const entry of entries) {
        const w = entry.contentRect.width;
        entry.target.dataset.size = w < 480 ? 'sm' : w < 900 ? 'md' : 'lg';
    }
    rerenderLists();
    fitAll();
});

function scan() {
    if (!ro) return;
    document.querySelectorAll('[data-kw-calendar]').forEach((root) => {
        if (!root.__kwObserved) {
            root.__kwObserved = true;
            ro.observe(root);
        }
    });
}

onLivewire(() => {
    window.Livewire.hook('morphed', scan);
    scan();
});
document.addEventListener('DOMContentLoaded', scan);
