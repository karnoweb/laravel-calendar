<!doctype html>
<html lang="{{ session('demo_locale', 'en') }}" dir="{{ session('demo_locale') === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal calendar</title>
    <style>
        html,body{height:100%;margin:0;overflow:hidden;background:#fff}
        @media (prefers-color-scheme:dark){html,body{background:#202124}}
        /* a full page: the calendar fills exactly what is left, the page itself never scrolls */
        .app{height:100dvh;display:flex;flex-direction:column}
        .app__bar{flex:none;padding:8px 16px;font:14px system-ui;border-bottom:1px solid #dadce0}
        .app__main{flex:1 1 auto;min-height:0}
    </style>@livewireStyles
</head>
<body>
    <div class="app">
        <div class="app__bar"><a href="/calendar/dashboard">Dashboard demo</a></div>
        <div class="app__main"><livewire:calendar::calendar calendar-key="personal" /></div>
    </div>
    @livewireScripts
</body>
</html>