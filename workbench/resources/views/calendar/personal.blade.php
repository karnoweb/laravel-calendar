@php
    $locale = session('demo_locale', 'en');
    $isRtl = $locale === 'fa';
    $isLight = session('demo_theme', 'luxe') === 'light';
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="{{ $isLight ? 'light' : 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isRtl ? 'تقویم شخصی' : 'Personal calendar' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        html,body{height:100%;margin:0;overflow:hidden;background:#090d16;font:14px 'Vazirmatn',system-ui,-apple-system,sans-serif;color:#f1f5f9}
        html.light,html.light body{background:#f8fafc;color:#0f172a}
        .app{height:100dvh;display:flex;flex-direction:column}
        .app__bar{flex:none;display:flex;align-items:center;justify-content:space-between;padding:8px 16px;font-size:13px;border-bottom:1px solid rgba(148,163,184,.14);background:#0f1624}
        html.light .app__bar{background:#fff;border-color:rgba(15,23,42,.08)}
        .app__bar a{color:#60a5fa;text-decoration:none;font-weight:700}
        .app__actions{display:flex;align-items:center;gap:8px}
        .app__btn{padding:4px 10px;border-radius:7px;border:1px solid rgba(148,163,184,.2);background:rgba(15,23,42,.6);color:#cbd5e1;font:inherit;font-size:12px;cursor:pointer;text-decoration:none}
        html.light .app__btn{background:#f8fafc;color:#334155;border-color:rgba(15,23,42,.12)}
        .app__main{flex:1 1 auto;min-height:0}
    </style>@livewireStyles
</head>
<body>
    <div class="app">
        <div class="app__bar">
            <a href="/calendar/dashboard">{{ $isRtl ? 'داشبورد تقویم' : 'Dashboard demo' }}</a>
            <div class="app__actions">
                <button type="button" class="app__btn" onclick="window.wbToggleTheme ? window.wbToggleTheme() : (document.documentElement.classList.toggle('dark'),document.documentElement.classList.toggle('light'))">
                    {{ $isRtl ? 'تغییر پوسته' : 'Toggle Theme' }}
                </button>
                <a class="app__btn" href="/calendar/personal?locale={{ $isRtl ? 'en' : 'fa' }}">
                    {{ $isRtl ? 'English' : 'فارسی' }}
                </a>
            </div>
        </div>
        <div class="app__main"><livewire:calendar::calendar calendar-key="personal" :locale="$locale" /></div>
    </div>
    @include('workbench::calendar.test-fab')
    @livewireScripts
</body>
</html>
