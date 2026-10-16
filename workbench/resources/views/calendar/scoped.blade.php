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
    <title>{{ $title ?? ($isRtl ? 'تقویم' : 'Calendar') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        html,body{height:100%;margin:0;background:#090d16;font:14px 'Vazirmatn',system-ui,-apple-system,sans-serif;color:#f1f5f9;-webkit-font-smoothing:antialiased}
        html.light,html.light body{background:#f1f5f9;color:#0f172a}
        .page{height:100dvh;display:flex;flex-direction:column;padding:12px 16px;box-sizing:border-box;gap:10px}
        .page__nav{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
        .page__links{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .page__link{padding:5px 12px;border-radius:8px;background:rgba(59,130,246,.14);border:1px solid rgba(59,130,246,.32);color:#60a5fa;text-decoration:none;font-size:12.5px;font-weight:700}
        .page__body{flex:1 1 auto;min-height:0;display:flex;flex-direction:column}
        .page__body>[wire\:id]{flex:1 1 auto;min-height:0}
    </style>@livewireStyles
</head>
<body>
    <div class="page">
        <header class="page__nav">
            <strong>{{ $title ?? ($isRtl ? 'تقویم' : 'Calendar') }}</strong>
            <nav class="page__links">
                <a class="page__link" href="/calendar/comprehensive">{{ $isRtl ? 'تقویم جامع (همه منابع)' : 'Comprehensive (All)' }}</a>
                <a class="page__link" href="/calendar/tasks">{{ $isRtl ? 'فقط وظایف (Tasks)' : 'Tasks Only' }}</a>
                <a class="page__link" href="/calendar/classes">{{ $isRtl ? 'فقط کلاس‌ها (Classes)' : 'Classes Only' }}</a>
                <a class="page__link" href="/calendar/dashboard">{{ $isRtl ? 'داشبورد چندمنبعی' : 'Multi-Source Dashboard' }}</a>
            </nav>
        </header>
        <div class="page__body">
            <livewire:calendar::full-calendar
                :calendar-key="$calendarKey"
                :sources="$sources ?? null"
                :locale="$locale"
            />
        </div>
    </div>
    @include('workbench::calendar.test-fab')
    @livewireScripts
</body>
</html>
