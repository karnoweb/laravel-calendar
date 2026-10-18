# رفع خطا

## نصب و بوت

| علامت | علت | بررسی / راه‌حل |
| --- | --- | --- |
| `Class "Karnoweb\Calendar\..." not found` | autoload | `composer dump-autoload` |
| Provider ثبت نشده | discovery خاموش | `php artisan package:discover` یا ثبت دستی `CalendarServiceProvider` |
| Livewire component نامعتبر | Livewire 3 | نسخه 4 لازم است (`composer.json` پکیج) |

## دسترسی و محدوده

| علامت | علت | راه‌حل |
| --- | --- | --- |
| `CalendarAccessDenied` | `ScopeResolver` یا مهمان | `auth` + binding `ScopeResolver` |
| مهمان روی تقویم خصوصی | `allow_public_calendars` و `allowAnonymousRead` | هر دو برای خواندن مهمان باید روشن باشند؛ نوشتن مهمان همیشه بسته |
| `sources` خالی در embed | `:sources="[]"` | آرایهٔ خالی عمداً «هیچ منبع» است؛ خطای دسترسی |

## تعریف تقویم

| پیام | علت |
| --- | --- |
| `Unknown registered calendar model source` | `registerModel` قبل از Factory انجام نشده |
| `Calendar requires at least one event source` | `modelSources([])` یا `sources` خالی |
| `Duplicate event source` | همان کلید دو بار در builder |
| `Invalid calendar key` | کلید باید `^[a-z][a-z0-9._-]{1,79}$` |

## داده و کوئری

| علامت | علت |
| --- | --- |
| DB پر است، UI خالی | `scope_key` / `scopeField` با `CalendarContext::scopeKey` یکی نیست |
| رویداد tenant دیگر | فیلتر scope در SQL اعمال نمی‌شود اگر ستون اشتباه باشد |
| جست‌وجو نتیجه نمی‌دهد | فیلد در `searchableFields` نیست |

## عملیات (نوشتن)

| وضعیت | معنی |
| --- | --- |
| `ReadOnly` | منبع read-only، مدل `readOnly`، یا feature سراسری خاموش |
| `Conflict` | `version` یا ETag Google |
| `Forbidden` | policy / `authorize` / توکن Google |
| `Unsupported` | action برای آن منبع (مثلاً create روی `holidays`) |
| Unknown outcome | خطای غیرمنتظره بعد از write؛ UI را refresh کنید؛ journal میزبان را چک کنید |

## UI و دارایی

| علامت | علت |
| --- | --- |
| بدون CSS | `assets.mode=published` بدون `vendor:publish --tag=calendar-assets` |
| ارتفاع صفر / layout شکسته | والد flex بدون `min-height: 0` |
| 404 روی view/layout | مقدار نامعتبر `views` / `layout` / `locale` در embed |

## Google

| علامت | علت |
| --- | --- |
| منبع خالی | `GoogleAccessTokenResolver` → `null` |
| نوشتن کار نمی‌کند | `CALENDAR_GOOGLE_WRITABLE=false` یا scope فقط readonly |
| Handler سفارشی | delegate به `GoogleCalendarHandler` برای `source === 'google'` |

## تست

```bash
composer test
composer analyse
```

`composer lint` (`pint --test`) ممکن است روی فایل‌های قدیمی مخزن fail شود؛ فایل‌های مستندات جدید با Pint سازگارند.

جزئیات سناریوها: [TEST-SCENARIOS.md](TEST-SCENARIOS.md).
