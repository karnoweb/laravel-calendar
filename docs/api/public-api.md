# API عمومی (مصرف‌کننده پکیج)

Namespace پایه: `Karnoweb\Calendar\`. موارد زیر قراردادهایی هستند که میزبان باید پیاده یا bind کند؛ جزئیات داخلی (`ReadDispatcher` ، grid builders و غیره) عمداً حذف شده‌اند.

## Facade و Manager

### `Karnoweb\Calendar\Facades\Calendar`

Alias در `composer.json`: `Calendar`. هدف: `CalendarManager`.

| متد | امضا | توضیح |
| --- | --- | --- |
| `register` | `register(string $key, string $factoryClass): void` | `$factoryClass` باید `CalendarFactory` باشد؛ کلاس ثبت شود نه closure |
| `registerModel` | `registerModel(string\|class-string $keyOrModel, ?string $modelClass = null): string` | مدل با `HasCalendarEvents` |
| `registerModels` | `registerModels(array $models): void` | map `sourceKey => Model::class` |
| `make` | `make(): CalendarBuilder` | builder تازه برای تعریف ad-hoc (معمولاً در Factory استفاده می‌شود) |

Factory روی **هر درخواست Livewire** دوباره resolve می‌شود.

## قراردادها (Contracts)

### `ScopeResolver`

```php
public function resolve(string $calendarKey): CalendarContext;
```

- **پیش‌شرط:** actor/tenant از session یا auth سرور؛ نه از query string تقویم.
- **خطا:** `CalendarAccessDenied` → خواندن/نوشتن رد.

### `TimezoneResolver`

وقتی timezone viewer با `config('calendar.timezone')` یکی نیست. پیش‌فرض: `AppTimezoneResolver`.

### `EventProvider`

```php
public function fetch(EventQuery $query, CalendarContext $context): EventPage;
public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData;
```

- کوئری و `find` باید داخل `$context->scopeKey` بمانند.
- `EventQuery` شامل بازه، pagination، search، filters.

### `ActionHandler`

```php
public function execute(ActionCommand $command, CalendarContext $context): ActionResult;
```

- یک handler برای هر تقویم؛ می‌تواند به handler دیگر (مثلاً `GoogleCalendarHandler`) delegate کند.
- پیش‌فرض Eloquent: `EloquentActionHandler` (خودکار با `modelSources`).

### `GoogleAccessTokenResolver`

```php
public function accessToken(CalendarContext $context): ?string;
```

`null` → منبع Google بدون رویداد؛ نوشتن → `Forbidden`.

### `CalendarFactory`

```php
public function make(CalendarContext $context): CalendarDefinition;
```

## `CalendarBuilder` (fluent)

| متد | پارامترها | بازگشت | نکته |
| --- | --- | --- | --- |
| `key` | `string $key` | `self` | الگوی کلید در validator |
| `sources` | `array $sources` | `self` | reset کامل منابع |
| `modelSources` | `?array $keys = null` | `self` | `null` = همهٔ مدل‌های ثبت‌شده؛ `[]` = هیچ |
| `addModelSource` | `string $key, array $overrides = []` | `self` | overrides: `readOnly`, `label`, `color`, … |
| `addSource` | `string $key, string\|array $declaration` | `self` | `provider` باید `EventProvider` |
| `google` | `bool $enabled = true, string $label = 'Google Calendar'` | `self` | کلید `google` |
| `holidays` | `bool $enabled = true, string $label = '...'` | `self` | read-only، config |
| `actions` | `class-string<ActionHandler> $handlerClass` | `self` | غیرفعال کردن auto handler |
| `settings` | `CalendarSettings $settings` | `self` | |
| `views` | `CalendarViews $views` | `self` | |
| `filters` | `list<CalendarFilterData> $filters` | `self` | |
| `build` | — | `CalendarDefinition` | حداقل یک منبع |

**Exception:** `InvalidCalendarDefinition` در build.

## `CalendarModelDefinition` (DTO)

ساخته‌شده در `Model::calendarDefinition()`. فیلدهای پرکاربرد:

| فیلد | نوع | الزامی | اثر |
| --- | --- | --- | --- |
| `sourceKey` | string | بله | کلید منبع |
| `startField` / `endField` | string\|null | start بله | mapping ستون‌ها |
| `defaultDurationMinutes` | int | اگر end null | |
| `scopeField` | string\|null | توصیه‌شده | فیلتر SQL |
| `ownerField` | string\|null | خیر | محدودیت مالک |
| `readOnly` | bool | پیش‌فرض false | |
| `searchableFields` | array | خیر | |
| `statusOptions` / `categoryOptions` | array | خیر | فیلتر UI |
| `capabilitiesResolver` | Closure | خیر | `ActionCapabilities` |
| `authorize` | Closure | خیر | قبل از write |
| `recurrenceRuleField` | string\|null | برای RRULE | |

## کامپوننت‌های Livewire

| نام | تگ Blade | ورودی اصلی |
| --- | --- | --- |
| `Karnoweb\Calendar\Livewire\Calendar` | `calendar::calendar` | `calendar-key` (الزامی) |
| `FullCalendar` | `calendar::full-calendar` | همان |
| `MiniMonth` | `calendar::mini-month` | همان |
| `UpcomingAgenda` | `calendar::upcoming-agenda` | `calendar-key`, `limit` |

ورودی‌های embed (محدودکننده، نه گسترش‌دهنده): `layout`, `views`, `default-view`, `systems`, `system`, `sources`, `preset-filters`, `locale`, `url-sync`, `url-prefix`. جدول کامل: [USAGE-FA.md](../USAGE-FA.md).

## رویداد

### `Karnoweb\Calendar\Events\CalendarEventMutated`

بعد از mutation موفق در لایهٔ پکیج dispatch می‌شود (میزبان برای sync UI دیگر).

## هویت رویداد

سه‌تایی `(sourceKey, eventId, occurrenceKey)`. مرجع encoded در API داخلی Livewire؛ handler باید `EventReference` را همان‌طور که provider ساخت بپذیرد.

## مثال حداقلی (Eloquent)

```php
Calendar::registerModel('tasks', Task::class);

app(CalendarManager::class)->register('my.calendar', MyCalendarFactory::class);
```

```blade
<livewire:calendar::calendar calendar-key="my.calendar" />
```

سناریوهای کامل: [examples/README.md](../examples/README.md).
