# شروع سریع (یکپارچه‌سازی کامل)

## هدف

نصب پکیج، تعریف محدودهٔ داده (`ScopeResolver`)، ثبت یک مدل Eloquent به‌عنوان منبع `tasks`، ساخت Factory با کلید `my.calendar`، و نمایش `<livewire:calendar::calendar>` در Blade.

## پیش‌نیازها

- PHP ^8.3 ، Laravel 13 ، Livewire 4
- جدول `tasks` با ستون‌های `scope_key` ، `scheduled_for` ، `title` ، `status` ، `version` (مطابق migration نمونه در [task-manager.md](examples/task-manager.md))
- کاربر احراز هویت‌شده در برنامهٔ میزبان (برای `ScopeResolver`)

---

## مرحله ۱: نصب

از ریشهٔ پروژهٔ Laravel:

```bash
composer require karnoweb/laravel-calendar
php artisan vendor:publish --tag=calendar-config
```

`Karnoweb\Calendar\CalendarServiceProvider` از auto-discovery ثبت می‌شود (`composer.json` → `extra.laravel.providers`).

---

## مرحله ۲: `ScopeResolver`

فایل: `app/Calendar/AppScopeResolver.php`

```php
<?php

declare(strict_types=1);

namespace App\Calendar;

use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;

final class AppScopeResolver implements ScopeResolver
{
    public function resolve(string $calendarKey): CalendarContext
    {
        $user = auth()->user() ?? throw new CalendarAccessDenied('Login required.');

        $tenantId = (string) ($user->tenant_id ?? 'default');
        $scopeKey = "t{$tenantId}:u{$user->id}";

        return new CalendarContext(
            calendarKey: $calendarKey,
            actorId: (string) $user->id,
            scopeKey: $scopeKey,
            locale: app()->getLocale(),
            timezone: config('app.timezone'),
            preferenceKey: $calendarKey,
        );
    }
}
```

در `app/Providers/AppServiceProvider.php` → `register()`:

```php
use Karnoweb\Calendar\Contracts\ScopeResolver;
use App\Calendar\AppScopeResolver;

$this->app->singleton(ScopeResolver::class, AppScopeResolver::class);
```

---

## مرحله ۳: مدل و ثبت منبع

مدل با `HasCalendarEvents` و `calendarDefinition()` — جزئیات کامل در [examples/task-manager.md](examples/task-manager.md).

در `boot()` همان Provider یا یک `CalendarServiceProvider` میزبان:

```php
use Karnoweb\Calendar\Facades\Calendar;
use App\Models\Task;

Calendar::registerModel('tasks', Task::class);
```

---

## مرحله ۴: Factory و ثبت کلید تقویم

فایل: `app/Calendars/MyCalendarFactory.php`

```php
<?php

declare(strict_types=1);

namespace App\Calendars;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Support\CalendarBuilder;

final class MyCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->modelSources(['tasks'])
            ->build();
    }
}
```

ثبت (در `boot()`):

```php
use Karnoweb\Calendar\Support\CalendarManager;
use App\Calendars\MyCalendarFactory;

$this->app->make(CalendarManager::class)->register('my.calendar', MyCalendarFactory::class);
```

`modelSources(['tasks'])` به‌طور خودکار `EloquentEventProvider` و `EloquentActionHandler` را برای همان منبع می‌بندد.

---

## مرحله ۵: Blade

فایل: `resources/views/calendar/my.blade.php`

```blade
<div style="height: calc(100dvh - 64px); min-height: 0;">
    <livewire:calendar::calendar calendar-key="my.calendar" />
</div>
```

Route (میزبان):

```php
Route::middleware(['web', 'auth'])->get('/calendar', fn () => view('calendar.my'));
```

---

## مرحله ۶: اجرا و بررسی

```bash
php artisan serve
```

1. با کاربری که `scope_key` رکوردها با خروجی resolver هم‌خوان است وارد شوید.
2. `/calendar` را باز کنید.
3. رویدادهای `tasks` در نمای ماه دیده می‌شوند؛ drag روی وظیفهٔ `movable` باید `scheduled_for` را به‌روز کند.

تأیید خودکار (در مخزن پکیج):

```bash
composer test
```

---

## مرحله ۷: خطاهای محتمل

| علامت | علت | راه‌حل |
| --- | --- | --- |
| صفحه خالی / `CalendarAccessDenied` | `ScopeResolver` یا کاربر مهمان | `auth` middleware و binding resolver |
| `Unknown registered calendar model source [tasks]` | مدل ثبت نشده | `Calendar::registerModel('tasks', ...)` قبل از Factory |
| `Calendar requires at least one event source` | `modelSources([])` یا کلید اشتباه | حداقل یک منبع معتبر در `build()` |
| رویداد نیست ولی در DB هست | `scope_key` SQL با resolver یکی نیست | هنگام insert همان `scopeKey` را بنویسید |
| UI بدون استایل | `assets.mode=published` بدون publish | `calendar-assets` یا `inline` (پیش‌فرض) |

راهنمای ورودی‌های Livewire: [USAGE-FA.md](USAGE-FA.md). API قراردادها: [api/public-api.md](api/public-api.md).
