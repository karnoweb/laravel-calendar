@php
    $locale = session('demo_locale', 'fa');
    $isRtl = $locale === 'fa';
    $isLight = session('demo_theme', 'luxe') === 'light';
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="{{ $isLight ? 'light' : 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isRtl ? 'داشبورد تقویم' : 'Calendar Dashboard' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        html,body{height:100%;margin:0;overflow:hidden;background:#090d16;font:14px 'Vazirmatn',system-ui,-apple-system,sans-serif;color:#f1f5f9;-webkit-font-smoothing:antialiased}
        html.light,html.light body{background:#f1f5f9;color:#0f172a}
        .dash{height:100dvh;display:grid;grid-template-columns:repeat(12,minmax(0,1fr));grid-template-rows:auto minmax(0,1fr) minmax(0,1fr);gap:14px;padding:14px 16px;box-sizing:border-box}
        .dash__header{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:2px 4px}
        .dash__brand{display:flex;align-items:center;gap:12px}
        .dash__title{margin:0;font-size:19px;font-weight:800;letter-spacing:-0.01em}
        .dash__link{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:8px;background:rgba(59,130,246,.14);border:1px solid rgba(59,130,246,.32);color:#60a5fa;text-decoration:none;font-size:12.5px;font-weight:700;transition:all .15s}
        .dash__link:hover{background:rgba(59,130,246,.24);color:#93c5fd}
        .dash__actions{display:flex;align-items:center;gap:8px}
        .dash__btn{padding:5px 11px;border-radius:8px;border:1px solid rgba(148,163,184,.2);background:rgba(15,23,42,.65);color:#cbd5e1;font:inherit;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none}
        html.light .dash__btn{background:#fff;color:#334155;border-color:rgba(15,23,42,.12)}
        .card{background:#0f1624;border:1px solid rgba(148,163,184,.12);border-radius:16px;overflow:hidden;min-height:0;display:flex;flex-direction:column;box-shadow:0 16px 40px -12px rgba(0,0,0,.55)}
        html.light .card{background:#ffffff;border-color:rgba(15,23,42,.08);box-shadow:0 10px 30px -10px rgba(15,23,42,.07)}
        .card>[wire\:id]{flex:1 1 auto;min-height:0;min-width:0}
        .a{grid-column:span 9;grid-row:2/4}
        .b,.c{height:auto;align-self:start}
        .b{grid-column:span 3;grid-row:2/3}
        .c{grid-column:span 3;grid-row:3/4}
        @media (max-width:1100px){.a{grid-column:span 8}.b,.c{grid-column:span 4}}
        @media (max-width:900px){.a,.b,.c{grid-column:1/-1;grid-row:auto}.dash{grid-template-rows:auto;overflow:auto;height:auto;min-height:100dvh}.a{height:72dvh}.b,.c{height:auto}html,body{overflow:auto}}
    </style>@livewireStyles
</head>
<body>
    <div class="dash">
        <header class="dash__header">
            <div class="dash__brand">
                <h1 class="dash__title">{{ $isRtl ? 'داشبورد' : 'Dashboard' }}</h1>
                <a class="dash__link" href="/calendar/personal">{{ $isRtl ? 'تمام‌صفحه' : 'Full page' }}</a>
                <a class="dash__link" href="/calendar/comprehensive">{{ $isRtl ? 'تقویم جامع' : 'Comprehensive' }}</a>
                <a class="dash__link" href="/calendar/tasks">{{ $isRtl ? 'فقط تسک‌ها' : 'Tasks Only' }}</a>
                <a class="dash__link" href="/calendar/classes">{{ $isRtl ? 'فقط کلاس‌ها' : 'Classes Only' }}</a>
            </div>
            <div class="dash__actions">
                <button type="button" class="dash__btn" onclick="window.wbToggleTheme ? window.wbToggleTheme() : (document.documentElement.classList.toggle('dark'),document.documentElement.classList.toggle('light'))">
                    {{ $isRtl ? 'تغییر پوسته (تاریک / روشن)' : 'Toggle Theme' }}
                </button>
                <a class="dash__btn" href="/calendar/dashboard?locale={{ $isRtl ? 'en' : 'fa' }}">
                    {{ $isRtl ? 'English (LTR)' : 'فارسی (RTL)' }}
                </a>
            </div>
        </header>
        <div class="card a">
            <livewire:calendar::full-calendar
                calendar-key="comprehensive"
                :url-sync="false"
                :views="['month', 'week', 'day', 'list']"
                :systems="['jalali', 'gregorian']"
                :locale="$locale"
            />
        </div>
        <div class="card b">
            <livewire:calendar::mini-month
                calendar-key="comprehensive"
                :sources="['classes']"
                :systems="[$isRtl ? 'jalali' : 'gregorian']"
                :locale="$locale"
            />
        </div>
        <div class="card c">
            <livewire:calendar::upcoming-agenda
                calendar-key="comprehensive"
                :sources="['classes']"
                :systems="[$isRtl ? 'jalali' : 'gregorian']"
                :locale="$locale"
            />
        </div>
    </div>
    @include('workbench::calendar.test-fab')
    @livewireScripts
</body>
</html>
