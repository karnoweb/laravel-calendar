# سناریو: LMS — کلاس‌های آموزشی

## هدف

نمایش **جلسات کلاس** با بازهٔ `starts_at` / `ends_at` (شامل رویداد چندروزه و all-day)، فقط برای tenant/کاربر فعلی. مرجع workbench: `EducationalClass` ، کلید `education.calendar` / `classes_only`.

## پیش‌نیازها

- `ScopeResolver` فعال
- دانش‌آموز یا مدرس احراز هویت‌شده

---

## مرحله ۱: Migration

```php
Schema::create('educational_classes', function (Blueprint $table): void {
    $table->id();
    $table->string('scope_key', 191)->index();
    $table->string('title', 191);
    $table->string('instructor_name', 120)->nullable();
    $table->text('description')->nullable();
    $table->string('location', 191)->nullable();
    $table->string('status', 24)->default('scheduled');
    $table->string('category', 32)->default('math');
    $table->dateTime('starts_at');
    $table->dateTime('ends_at')->nullable();
    $table->boolean('all_day')->default(false);
    $table->boolean('movable')->default(true);
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->index(['scope_key', 'starts_at', 'ends_at']);
});
```

---

## مرحله ۲: مدل

فایل: `app/Models/EducationalClass.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarModelDefinition;

class EducationalClass extends Model
{
    use HasCalendarEvents;

    protected $table = 'educational_classes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'movable' => 'boolean',
            'version' => 'integer',
        ];
    }

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'classes',
            label: ['fa' => 'کلاس‌های آموزشی', 'en' => 'Classes'],
            color: 'green',
            startField: 'starts_at',
            endField: 'ends_at',
            defaultDurationMinutes: 90,
            scopeField: 'scope_key',
            searchableFields: ['title', 'instructor_name', 'location', 'description'],
            statusOptions: [
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
            categoryOptions: [
                'math' => 'Mathematics',
                'science' => 'Science',
                'language' => 'Language',
            ],
            capabilitiesResolver: static function (self $class, CalendarContext $ctx, ActionCapabilities $base): ActionCapabilities {
                if ($class->status === 'cancelled' || ! $class->movable) {
                    return new ActionCapabilities(true, false, false, false, false);
                }

                return $base;
            },
        );
    }
}
```

### قوانین بازه (رفتار واقعی پکیج)

- **پایان exclusive برای all-day چندروزه:** شروع ۱۶ اکتبر و پایان ۱۹ اکتبر → سه روز تقویمی ۱۶، ۱۷، ۱۸ (تست: `DomainCalendarAcceptanceTest` در workbench).
- **`ends_at` null:** مدت = `defaultDurationMinutes` (۹۰ دقیقه).
- **`cancelled`:** فقط خواندنی در تقویم.

---

## مرحله ۳: ثبت و Factory

```php
$manager->registerModel('classes', EducationalClass::class);
$manager->register('education.calendar', EducationCalendarFactory::class);
```

`app/Calendars/EducationCalendarFactory.php`:

```php
<?php

declare(strict_types=1);

namespace App\Calendars;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\CalendarView;
use Karnoweb\Calendar\Support\CalendarBuilder;

final class EducationCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        $isFa = $context->locale === 'fa';

        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->modelSources(['classes'])
            ->settings(new CalendarSettings(
                defaultView: CalendarView::Week,
                enabledViews: [CalendarView::Month, CalendarView::Week, CalendarView::Day, CalendarView::List],
                calendarSystem: $isFa ? CalendarSystem::Jalali : CalendarSystem::Gregorian,
                enabledSystems: [CalendarSystem::Jalali, CalendarSystem::Gregorian],
                firstWeekday: $isFa ? 6 : 1,
            ))
            ->build();
    }
}
```

تقویم آموزشی روی جدول `tasks` کوئری **نمی‌زند** — فقط منابعی که Factory اضافه کرده (`modelSources(['classes'])`).

---

## مرحله ۴: دادهٔ نمونه

```php
EducationalClass::create([
    'scope_key' => $ctx->scopeKey,
    'title' => 'ریاضی پایه — فصل ۳',
    'instructor_name' => 'Dr. Ahmadi',
    'location' => 'Room 12',
    'status' => 'scheduled',
    'category' => 'math',
    'starts_at' => '2026-10-20 08:00:00',
    'ends_at' => '2026-10-20 09:30:00',
    'movable' => true,
    'version' => 1,
]);
```

کلاس چندروزه (camp):

```php
EducationalClass::create([
    'scope_key' => $ctx->scopeKey,
    'title' => 'اردو علمی',
    'starts_at' => '2026-10-16 00:00:00',
    'ends_at' => '2026-10-19 00:00:00',
    'all_day' => true,
    'status' => 'scheduled',
    'movable' => true,
    'version' => 1,
]);
```

---

## مرحله ۵: Blade

صفحهٔ هفته برای برنامهٔ کلاسی:

```blade
<div style="height: 100dvh; display:flex; flex-direction:column">
    <header class="h-16">LMS Schedule</header>
    <div style="flex:1; min-height:0">
        <livewire:calendar::full-calendar
            calendar-key="education.calendar"
            locale="fa"
            system="jalali"
        />
    </div>
</div>
```

Agenda کنار داشبورد:

```blade
<livewire:calendar::upcoming-agenda calendar-key="education.calendar" :limit="10" locale="fa" />
```

---

## مرحله ۶: اجرا و بررسی

Workbench:

- `http://127.0.0.1:8765/calendar/education-calendar`
- `http://127.0.0.1:8765/calendar/classes`

```bash
vendor/bin/phpunit --filter DomainCalendarAcceptanceTest
```

انتظار: SQL فقط `workbench_educational_classes` (یا جدول شما)؛ resize بازهٔ `starts_at`/`ends_at` را به‌روز می‌کند؛ `cancelled` از drag جلوگیری می‌کند.

---

## مرحله ۷: خطاهای محتمل

| علامت | علت | راه‌حل |
| --- | --- | --- |
| کلاس یک روز کم/زیاد در all-day | `ends_at` inclusive اشتباه | پایان را **روز بعد از آخرین روز نمایش** بگذارید (exclusive) |
| فیلتر category در UI نیست | فقط اگر `categoryOptions` در definition باشد | همان آرایه را در `CalendarModelDefinition` تعریف کنید |
| کلاس‌های tenant دیگر دیده می‌شوند | `scope_key` اشتباه | resolver و insert را یکسان کنید |

مرجع: `workbench/app/Models/EducationalClass.php` ، `workbench/app/Calendars/ClassesOnlyCalendarFactory.php`.
