export function announce(root, message) {
    const live = root && root.querySelector('[data-kw-live]');
    if (!live) return;
    live.textContent = '';
    // Re-set on the next frame so identical consecutive messages are announced again.
    requestAnimationFrame(() => {
        live.textContent = message;
    });
}
