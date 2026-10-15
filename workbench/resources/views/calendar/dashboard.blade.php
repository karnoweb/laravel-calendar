<!doctype html>
<html lang="{{ session('demo_locale', 'en') }}" dir="{{ session('demo_locale') === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard with embedded calendars</title>
    <style>
        html,body{height:100%;margin:0;overflow:hidden;background:#f1f3f4;font:14px system-ui}
        .dash{height:100dvh;display:grid;grid-template-columns:repeat(12,minmax(0,1fr));grid-template-rows:auto minmax(0,1fr) minmax(0,1fr);gap:12px;padding:12px;box-sizing:border-box}
        .dash h1{grid-column:1/-1;margin:0;font-size:18px}
        .card{background:#fff;border:1px solid #dadce0;border-radius:8px;overflow:hidden;min-height:0}
        .a{grid-column:span 8;grid-row:2/4}.b{grid-column:span 4}.c{grid-column:span 4}
        @media (max-width:900px){.a,.b,.c{grid-column:1/-1;grid-row:auto}.dash{grid-template-rows:auto;overflow:auto;height:auto;min-height:100dvh}.card{height:70dvh}html,body{overflow:auto}}
    </style>@livewireStyles
</head>
<body>
    <div class="dash">
        <h1>Dashboard <a href="/calendar/personal" style="font-size:13px">full page</a></h1>
        <div class="card a">
            <livewire:calendar::calendar calendar-key="personal" :url-sync="false" layout="compact" :views="['month', 'week']" :sources="['tasks', 'holidays']" />
        </div>
        <div class="card b">
            <livewire:calendar::calendar calendar-key="personal" :url-sync="false" layout="compact" :views="['list']" :sources="['holidays']" :systems="['jalali']" locale="fa" />
        </div>
        <div class="card c">
            <livewire:calendar::calendar calendar-key="personal" :url-sync="false" layout="compact" :views="['month']" :sources="['tasks']" :preset-filters="['status' => 'open']" :systems="['gregorian']" />
        </div>
    </div>
    @livewireScripts
</body>
</html>