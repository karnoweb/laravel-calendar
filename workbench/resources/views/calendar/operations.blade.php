<!doctype html>
<html lang="{{ session('demo_locale', 'en') }}" dir="{{ session('demo_locale') === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Operations calendar</title>
    <style>body{margin:0;background:#fff}@media (prefers-color-scheme:dark){body{background:#202124}}</style>@livewireStyles
</head>
<body>
    <main style="max-width: 1480px; margin: 8px auto; padding: 0 12px">
        <livewire:calendar::calendar calendar-key="operations" url-prefix="ops" />
    </main>
    @livewireScripts
</body>
</html>
