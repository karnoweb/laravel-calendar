# شروع سریع

## ۱. نصب

```bash
composer require karnoweb/laravel-calendar
php artisan vendor:publish --tag=calendar-config   # اختیاری
```

## ۲. تعریف تقویم (یک‌بار، سمت پروژه)

تقویم با یک Factory تعریف می‌شود. داده‌ها، دسترسی‌ها و ذخیره‌سازی همیشه در اختیار پروژه‌ی شماست.

```php
use Karnoweb\Calendar\Support\CalendarBuilder;

(new CalendarBuilder)
    ->key('personal')
    ->sources(['tasks' => ['provider' => TaskProvider::class, 'label' => 'کارها', 'color' => 'blue']])
    ->google(config('services.google.enabled'))   // تقویم گوگل (اختیاری)
    ->holidays()                                  // تعطیلات رسمی (اختیاری)
    ->actions(TaskHandler::class)
    ->build();
```

> `google()` و `holidays()` را **بعد از** `sources()` صدا بزنید (`sources()` لیست را از نو می‌سازد).

## ۳. نمایش به‌صورت صفحه‌ی کامل

کانتینر باید ارتفاع مشخص داشته باشد؛ تقویم همان را کامل پر می‌کند و صفحه هرگز اسکرول نمی‌شود.

```blade
<div style="height: calc(100dvh - 64px)">
    <livewire:calendar::calendar calendar-key="personal" />
</div>
```

## ۴. نمایش داخل داشبورد (بخش کوچک)

```blade
<div class="card" style="height: 360px">
    <livewire:calendar::calendar
        calendar-key="personal"
        layout="compact"
        :url-sync="false"
        :views="['month']"
        :sources="['tasks', 'holidays']"
        :preset-filters="['status' => 'open']"
        locale="fa"
        system="jalali"
    />
</div>
```

## ۵. اجرای دمو

```bash
php vendor/bin/testbench workbench:build   # یک‌بار
php vendor/bin/testbench serve --port=8765
```

- صفحه‌ی کامل: `/workbench/login/1/1?google=fake` سپس `/calendar/personal`
- داشبورد: `/calendar/dashboard`

جزئیات کامل: [USAGE-FA.md](USAGE-FA.md)
