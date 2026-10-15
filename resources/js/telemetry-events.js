// Re-publishes operation outcomes as a neutral window event for host analytics.
// Payload is status + operation id only: never titles, descriptions or any user text.
window.addEventListener('kw-calendar:result', (e) => {
    const d = e.detail && (e.detail[0] ?? e.detail);
    window.dispatchEvent(new CustomEvent('kw-calendar:telemetry', {
        detail: { status: String(d?.status ?? ''), operationId: String(d?.operationId ?? ''), at: Date.now() },
    }));
});
