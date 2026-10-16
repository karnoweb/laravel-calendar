@php
    $wbLocale = session('demo_locale', 'fa');
    $wbRtl = $wbLocale === 'fa';
    $wbDefaults = \Karnoweb\Calendar\Data\CalendarSettings::FEATURES;
    $wbFeatures = array_replace($wbDefaults, (array) session('demo_features', []));
    $wbTheme = (string) session('demo_theme', 'luxe');
    $wbGoogle = (bool) session('demo_google');
    $wbActor = (string) session('demo_actor', '11');

    $wbFeatureLabels = [
        'move' => $wbRtl ? 'جابه‌جایی رویدادها (Move / Drag)' : 'Move / Drag Events',
        'resize' => $wbRtl ? 'کشش زمانی (Resize / Stretch)' : 'Time Stretch / Resize',
        'confirmMove' => $wbRtl ? 'تأیید پیش از جابه‌جایی/کشش' : 'Confirm Move / Resize',
        'confirmDelete' => $wbRtl ? 'تأیید پیش از حذف' : 'Confirm Delete',
        'create' => $wbRtl ? 'ایجاد رویداد جدید (Create)' : 'Create Events',
        'edit' => $wbRtl ? 'ویرایش رویداد (Edit)' : 'Edit Events',
        'delete' => $wbRtl ? 'حذف رویداد (Delete)' : 'Delete Events',
        'recurrence' => $wbRtl ? 'رویدادهای تکرارشونده (Recurrence)' : 'Recurring Events',
        'search' => $wbRtl ? 'جستجو (Search)' : 'Search Box',
        'filters' => $wbRtl ? 'فیلترها و منابع (Filters)' : 'Filters & Sources',
        'viewSwitcher' => $wbRtl ? 'تغییر نما (ماه/هفته/روز/لیست)' : 'View Switcher',
        'systemToggle' => $wbRtl ? 'تغییر تقویم (شمسی/میلادی)' : 'Calendar System Toggle',
    ];
@endphp
<style>
    .wb-fab-wrap {
        position: fixed;
        inset-block-end: 20px;
        inset-inline-end: 20px;
        z-index: 9999;
        font: 13px/1.4 'Vazirmatn', system-ui, -apple-system, sans-serif;
        direction: {{ $wbRtl ? 'rtl' : 'ltr' }};
    }
    .wb-fab-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 999px;
        border: 1px solid rgba(59, 130, 246, 0.45);
        background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
        color: #ffffff;
        font: inherit;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        box-shadow: 0 10px 28px rgba(37, 99, 235, 0.45);
        transition: transform .15s, box-shadow .15s, filter .15s;
    }
    .wb-fab-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px rgba(37, 99, 235, 0.55);
        filter: brightness(1.06);
    }
    .wb-fab-panel {
        position: absolute;
        inset-block-end: calc(100% + 12px);
        inset-inline-end: 0;
        width: min(370px, calc(100vw - 32px));
        max-height: min(640px, calc(100dvh - 96px));
        display: flex;
        flex-direction: column;
        border-radius: 16px;
        background: #0f172a;
        border: 1px solid rgba(148, 163, 184, 0.22);
        color: #f1f5f9;
        box-shadow: 0 24px 54px rgba(0, 0, 0, 0.6);
        overflow: hidden;
    }
    html.light .wb-fab-panel {
        background: #ffffff;
        border-color: rgba(15, 23, 42, 0.12);
        color: #0f172a;
        box-shadow: 0 20px 48px rgba(15, 23, 42, 0.16);
    }
    .wb-fab-panel[hidden] { display: none !important; }
    .wb-fab-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-block-end: 1px solid rgba(148, 163, 184, 0.16);
        background: rgba(30, 41, 59, 0.5);
    }
    html.light .wb-fab-head {
        background: #f8fafc;
        border-color: rgba(15, 23, 42, 0.08);
    }
    .wb-fab-title {
        margin: 0;
        font-size: 14px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }
    .wb-fab-close {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
        opacity: .7;
        padding: 2px 6px;
        border-radius: 6px;
    }
    .wb-fab-close:hover { opacity: 1; background: rgba(148, 163, 184, 0.15); }
    .wb-fab-body {
        padding: 12px 14px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .wb-fab-section {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .wb-fab-label {
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
    html.light .wb-fab-label { color: #64748b; }
    .wb-fab-pills {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
    }
    .wb-fab-pill {
        padding: 6px 8px;
        border-radius: 8px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: rgba(30, 41, 59, 0.55);
        color: inherit;
        font: inherit;
        font-size: 11.5px;
        font-weight: 600;
        cursor: pointer;
        text-align: center;
        transition: all .14s;
    }
    html.light .wb-fab-pill {
        background: #f8fafc;
        border-color: rgba(15, 23, 42, 0.12);
    }
    html.light .wb-fab-pill.is-active,
    .wb-fab-pill.is-active {
        background: #2563eb;
        border-color: #3b82f6;
        color: #ffffff;
    }
    .wb-fab-list {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .wb-fab-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 6px 9px;
        border-radius: 9px;
        background: rgba(30, 41, 59, 0.35);
        border: 1px solid rgba(148, 163, 184, 0.1);
        cursor: pointer;
        user-select: none;
        transition: background-color .12s;
    }
    html.light .wb-fab-row {
        background: #f8fafc;
        border-color: rgba(15, 23, 42, 0.07);
    }
    .wb-fab-row:hover {
        background: rgba(59, 130, 246, 0.12);
    }
    .wb-fab-row__text {
        font-size: 12px;
        font-weight: 600;
    }
    .wb-fab-switch {
        position: relative;
        width: 34px;
        height: 19px;
        border-radius: 999px;
        background: rgba(100, 116, 139, 0.45);
        flex: none;
        transition: background-color .16s;
    }
    .wb-fab-switch.is-on { background: #2563eb; }
    .wb-fab-switch::after {
        content: "";
        position: absolute;
        top: 2px;
        inset-inline-start: 2px;
        width: 15px;
        height: 15px;
        border-radius: 50%;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        transition: inset-inline-start .16s;
    }
    .wb-fab-switch.is-on::after { inset-inline-start: 17px; }
    .wb-fab-foot {
        display: flex;
        gap: 6px;
        padding: 10px 14px;
        border-block-start: 1px solid rgba(148, 163, 184, 0.16);
        background: rgba(30, 41, 59, 0.4);
    }
    html.light .wb-fab-foot {
        background: #f8fafc;
        border-color: rgba(15, 23, 42, 0.08);
    }
    .wb-fab-foot .wb-fab-pill { flex: 1; }
</style>

<div class="wb-fab-wrap" data-wb-fab-root>
    <div class="wb-fab-panel" data-wb-fab-panel hidden>
        <header class="wb-fab-head">
            <h3 class="wb-fab-title">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>{{ $wbRtl ? 'پنل تنظیمات محیط تست' : 'Workbench Test Settings' }}</span>
            </h3>
            <button type="button" class="wb-fab-close" data-wb-fab-close aria-label="Close">×</button>
        </header>

        <div class="wb-fab-body">
            <div class="wb-fab-section">
                <span class="wb-fab-label">{{ $wbRtl ? 'پوسته (Theme)' : 'Theme' }}</span>
                <div class="wb-fab-pills">
                    <button type="button" class="wb-fab-pill {{ in_array($wbTheme, ['luxe', 'dark'], true) ? 'is-active' : '' }}" data-wb-theme="luxe">
                        {{ $wbRtl ? 'تاریک (Luxe)' : 'Dark (Luxe)' }}
                    </button>
                    <button type="button" class="wb-fab-pill {{ $wbTheme === 'light' ? 'is-active' : '' }}" data-wb-theme="light">
                        {{ $wbRtl ? 'روشن (Light)' : 'Light' }}
                    </button>
                    <button type="button" class="wb-fab-pill {{ $wbTheme === 'auto' ? 'is-active' : '' }}" data-wb-theme="auto">
                        {{ $wbRtl ? 'خودکار (Auto)' : 'Auto' }}
                    </button>
                </div>
            </div>

            <div class="wb-fab-section">
                <span class="wb-fab-label">{{ $wbRtl ? 'قابلیت‌های کامپوننت (Features)' : 'Component Features' }}</span>
                <div class="wb-fab-list">
                    @foreach ($wbFeatureLabels as $fKey => $fLabel)
                        @php $isOn = (bool) ($wbFeatures[$fKey] ?? true); @endphp
                        <div class="wb-fab-row" role="switch" tabindex="0" aria-checked="{{ $isOn ? 'true' : 'false' }}" data-wb-feature="{{ $fKey }}">
                            <span class="wb-fab-row__text">{{ $fLabel }}</span>
                            <span class="wb-fab-switch {{ $isOn ? 'is-on' : '' }}" data-wb-switch="{{ $fKey }}"></span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="wb-fab-section">
                <span class="wb-fab-label">{{ $wbRtl ? 'منابع و کاربر تست (Context)' : 'Sources & Test Actor' }}</span>
                <div class="wb-fab-row" role="switch" tabindex="0" aria-checked="{{ $wbGoogle ? 'true' : 'false' }}" data-wb-google>
                    <span class="wb-fab-row__text">{{ $wbRtl ? 'تقویم گوگل شبیه‌ساز (Fake Google)' : 'Fake Google Calendar' }}</span>
                    <span class="wb-fab-switch {{ $wbGoogle ? 'is-on' : '' }}" data-wb-google-switch></span>
                </div>
                <div class="wb-fab-pills" style="margin-top: 4px">
                    <button type="button" class="wb-fab-pill {{ $wbActor === '11' ? 'is-active' : '' }}" data-wb-actor="11">
                        {{ $wbRtl ? 'کاربر ۱۱ (کامل)' : 'Actor #11 (Full)' }}
                    </button>
                    <button type="button" class="wb-fab-pill {{ $wbActor === '33' ? 'is-active' : '' }}" data-wb-actor="33">
                        {{ $wbRtl ? 'کاربر ۳۳ (بدون حذف)' : 'Actor #33 (No Del)' }}
                    </button>
                    <button type="button" class="wb-fab-pill {{ $wbActor === '22' ? 'is-active' : '' }}" data-wb-actor="22">
                        {{ $wbRtl ? 'کاربر ۲۲ (مجزا)' : 'Actor #22 (Other)' }}
                    </button>
                </div>
            </div>
        </div>

        <footer class="wb-fab-foot">
            <button type="button" class="wb-fab-pill" data-wb-action="reset">
                {{ $wbRtl ? 'بازنشانی تنظیمات' : 'Reset Settings' }}
            </button>
            <button type="button" class="wb-fab-pill" data-wb-action="reseed">
                {{ $wbRtl ? 'بازنشانی داده‌ها' : 'Reseed Events' }}
            </button>
        </footer>
    </div>

    <button type="button" class="wb-fab-btn" data-wb-fab-trigger aria-expanded="false">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
        <span>{{ $wbRtl ? 'تنظیمات تست' : 'Test Settings' }}</span>
    </button>
</div>

<script>
(() => {
    const root = document.querySelector('[data-wb-fab-root]');
    if (!root) return;
    const trigger = root.querySelector('[data-wb-fab-trigger]');
    const panel = root.querySelector('[data-wb-fab-panel]');
    const closeBtn = root.querySelector('[data-wb-fab-close]');

    function applyDomTheme(theme) {
        const isLight = theme === 'light';
        document.documentElement.classList.toggle('light', isLight);
        document.documentElement.classList.toggle('dark', !isLight);
        document.querySelectorAll('[data-kw-calendar]').forEach((cal) => {
            cal.setAttribute('data-theme', theme);
        });
        root.querySelectorAll('[data-wb-theme]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.wbTheme === theme || (theme === 'dark' && btn.dataset.wbTheme === 'luxe'));
        });
    }

    // Apply initial session theme to html class on load
    applyDomTheme(@json($wbTheme));

    async function postSetting(params) {
        const url = new URL('/workbench/settings', window.location.origin);
        url.searchParams.set('json', '1');
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, String(v)));
        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return null;
        const data = await res.json();
        if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
            window.Livewire.dispatch('kw-calendar-refresh', { syncSettings: true });
        }
        return data;
    }

    function syncUi(data) {
        if (!data) return;
        applyDomTheme(data.theme);
        if (data.features) {
            Object.entries(data.features).forEach(([k, on]) => {
                const row = root.querySelector(`[data-wb-feature="${k}"]`);
                const sw = root.querySelector(`[data-wb-switch="${k}"]`);
                if (row) row.setAttribute('aria-checked', on ? 'true' : 'false');
                if (sw) sw.classList.toggle('is-on', !!on);
            });
        }
        const gRow = root.querySelector('[data-wb-google]');
        const gSw = root.querySelector('[data-wb-google-switch]');
        if (gRow) gRow.setAttribute('aria-checked', data.google ? 'true' : 'false');
        if (gSw) gSw.classList.toggle('is-on', !!data.google);
        root.querySelectorAll('[data-wb-actor]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.wbActor === String(data.actor));
        });
    }

    window.wbSetTheme = async (theme) => {
        applyDomTheme(theme);
        const data = await postSetting({ theme });
        syncUi(data);
    };

    window.wbToggleTheme = () => {
        const next = document.documentElement.classList.contains('light') ? 'luxe' : 'light';
        window.wbSetTheme(next);
    };

    trigger.addEventListener('click', () => {
        const open = panel.hidden;
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    closeBtn.addEventListener('click', () => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    });

    root.querySelectorAll('[data-wb-theme]').forEach((btn) => {
        btn.addEventListener('click', () => window.wbSetTheme(btn.dataset.wbTheme));
    });

    root.querySelectorAll('[data-wb-feature]').forEach((row) => {
        row.addEventListener('click', async () => {
            const key = row.dataset.wbFeature;
            const sw = root.querySelector(`[data-wb-switch="${key}"]`);
            const next = sw ? !sw.classList.contains('is-on') : true;
            if (sw) sw.classList.toggle('is-on', next);
            row.setAttribute('aria-checked', next ? 'true' : 'false');
            const data = await postSetting({ feature: key, enabled: next ? '1' : '0' });
            syncUi(data);
        });
    });

    const googleRow = root.querySelector('[data-wb-google]');
    if (googleRow) {
        googleRow.addEventListener('click', async () => {
            const sw = root.querySelector('[data-wb-google-switch]');
            const next = sw ? !sw.classList.contains('is-on') : true;
            if (sw) sw.classList.toggle('is-on', next);
            const data = await postSetting({ google: next ? 'fake' : 'off' });
            syncUi(data);
        });
    }

    root.querySelectorAll('[data-wb-actor]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const data = await postSetting({ actor: btn.dataset.wbActor });
            syncUi(data);
        });
    });

    root.querySelectorAll('[data-wb-action]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const data = await postSetting({ action: btn.dataset.wbAction });
            syncUi(data);
        });
    });
})();
</script>
