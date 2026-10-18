# سناریو: Google Calendar (منبع خارجی)

## هدف

نمایش رویدادهای تقویم Google کنار منابع داخلی (مثلاً `tasks`). OAuth و ذخیرهٔ refresh token **مالکیت میزبان** است؛ پکیج فقط `GoogleAccessTokenResolver` را صدا می‌زند و با `GoogleCalendarProvider` می‌خواند.

## پیش‌نیازها

- پروژهٔ Google Cloud با Calendar API فعال
- OAuth scope:
  - فقط خواندن: `https://www.googleapis.com/auth/calendar.readonly`
  - خواندن/نوشتن: `https://www.googleapis.com/auth/calendar.events`
- `ScopeResolver` و حداقل یک منبع دیگر (یا همان tasks) در Factory

---

## مرحله ۱: تنظیمات `.env`

```env
CALENDAR_GOOGLE_CALENDAR_ID=primary
CALENDAR_GOOGLE_WRITABLE=false
```

| متغیر | پیش‌فرض | اثر |
| --- | --- | --- |
| `CALENDAR_GOOGLE_CALENDAR_ID` | `primary` | شناسهٔ تقویم در Google API v3 |
| `CALENDAR_GOOGLE_WRITABLE` | `false` | تا `true` نشود، drag/create روی منبع `google` `ReadOnly` برمی‌گردد |

کلیدهای `config/calendar.php` → `google`: `writable` ، `timeout` (۸ ثانیه) ، `api_base`.

---

## مرحله ۲: `GoogleAccessTokenResolver`

فایل: `app/Calendar/UserGoogleTokenResolver.php`

```php
<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Models\User;
use Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver;
use Karnoweb\Calendar\Data\CalendarContext;

final class UserGoogleTokenResolver implements GoogleAccessTokenResolver
{
    public function accessToken(CalendarContext $context): ?string
    {
        if ($context->actorId === null) {
            return null;
        }

        $user = User::find($context->actorId);
        if ($user === null || $user->google_access_token === null) {
            return null;
        }

        // میزبان: refresh token را اینجا تازه کنید اگر منقضی شده.
        return $user->google_access_token;
    }
}
```

ثبت:

```php
use Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver;
use App\Calendar\UserGoogleTokenResolver;

$this->app->bind(GoogleAccessTokenResolver::class, UserGoogleTokenResolver::class);
```

**بدون توکن:** منبع `google` خالی است؛ خطای UI برای بقیهٔ منابع نیست.

---

## مرحله ۳: Factory — فعال‌کردن منبع `google`

```php
return (new CalendarBuilder)
    ->key($context->calendarKey)
    ->sources(['tasks' => ['provider' => TaskProvider::class, 'label' => 'Tasks', 'color' => 'blue']])
    ->google(true)
    ->holidays()
    ->actions(TaskHandler::class)
    ->build();
```

قوانین `CalendarBuilder`:

- `sources()` لیست را **از صفر** می‌سازد؛ `google()` و `holidays()` را **بعد از** `sources()` یا `modelSources()` بزنید.
- کلید منبع Google همیشه `google` (`GoogleCalendarProvider`).

با **فقط Eloquent**:

```php
return (new CalendarBuilder)
    ->key($context->calendarKey)
    ->modelSources(['tasks'])
    ->google(true)
    ->build();
```

در این حالت `EloquentActionHandler` برای `sourceKey === 'google'` خودکار به `GoogleCalendarHandler` delegate می‌کند (`EloquentActionHandler::execute`).

---

## مرحله ۴: Handler سفارشی — delegate نوشتن Google

اگر `actions(CustomHandler::class)` دارید، باید منبع `google` را خودتان به `GoogleCalendarHandler` بسپارید (الگوی workbench):

```php
use Karnoweb\Calendar\Google\GoogleCalendarHandler;

public function execute(ActionCommand $command, CalendarContext $context): ActionResult
{
    $source = $command->sourceKey ?? $command->reference?->sourceKey;

    if ($source === 'google') {
        return app(GoogleCalendarHandler::class)->execute($command, $context);
    }

    // ... منطق tasks / سایر منابع
}
```

مرجع: `workbench/app/Calendar/DemoTaskHandler.php` (خطوط ۴۲–۴۷).

---

## مرحله ۵: Blade

```blade
<livewire:calendar::calendar calendar-key="personal" />
```

در workbench تقویم `personal` منبع tasks + google + holidays دارد.

---

## مرحله ۶: اجرا و بررسی

### دمو بدون credential (workbench)

```bash
php vendor/bin/testbench serve --port=8765
```

1. `http://127.0.0.1:8765/workbench/login/11/101?google=fake`
2. `http://127.0.0.1:8765/calendar/personal`

`google=fake` → `DemoGoogleTokenResolver` API درون‌پردازشی (`FakeGoogleApi`) را نصب می‌کند و `calendar.google.writable` را برای دمو `true` می‌کند.

### توکن واقعی (workbench)

```env
WORKBENCH_GOOGLE_TOKEN=ya29....
```

سپس `?google=live` در login.

### رفتار مورد انتظار

| حالت | خواندن | نوشتن |
| --- | --- | --- |
| بدون resolver / توکن null | خالی | `Forbidden` |
| readonly scope + `writable=false` | رویدادها | `ReadOnly` |
| `writable=true` + token معتبر | رویدادها | create/update/move/delete روی Google |
| ETag stale | — | `Conflict` (HTTP 412 از Google) |

**محدودیت‌های مستندشده در README:** ایجاد RRULE در Google و ویرایش سری Google پشتیبانی کامل ندارد.

---

## مرحله ۷: خطاهای محتمل

| علامت | علت | راه‌حل |
| --- | --- | --- |
| Google هرگز پر نمی‌شود | resolver bind نشده یا توکن null | OAuth میزبان + `GoogleAccessTokenResolver` |
| drag روی Google بی‌اثر | `CALENDAR_GOOGLE_WRITABLE=false` | env را `true` کنید + scope `calendar.events` |
| `Unsupported` روی google | Handler سفارشی delegate نکرده | شاخه `google` → `GoogleCalendarHandler` |
| منبع google ناپدید بعد از `sources()` | ترتیب builder | بعد از `sources()` دوباره `->google(true)` |
| `GoogleCalendarException` | timeout / 5xx | `config('calendar.google.timeout')` یا retry در میزبان |

مرجع workbench:

- `workbench/app/Calendar/DemoGoogleTokenResolver.php`
- `workbench/app/Calendars/PersonalCalendarFactory.php`
- `workbench/app/Calendar/FakeGoogleApi.php`

OAuth flow در پکیج **وجود ندارد** — باید در Laravel Socialite یا SDK Google پیاده شود و توکن در DB کاربر ذخیره شود.
