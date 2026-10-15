// Client-side comfort for the recurrence editor. The server remains the only authority on rule validity.
document.addEventListener('input', (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'number' || !input.closest('[data-kw-recurrence]')) return;
    const min = input.min === '' ? 1 : Number(input.min);
    const max = input.max === '' ? 999 : Number(input.max);
    if (input.value === '') return;
    const v = Math.trunc(Number(input.value));
    if (Number.isNaN(v)) return;
    const clamped = Math.max(min, Math.min(max, v));
    if (clamped !== Number(input.value)) {
        input.value = String(clamped);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
});
