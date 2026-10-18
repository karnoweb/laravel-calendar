# سناریو: Task Manager

## هدف

تقویمی که فقط **وظایف** کاربر جاری را از جدول `tasks` می‌خواند و create / move / resize / delete را روی همان رکوردها اعمال می‌کند. الگوی مرجع در workbench: مدل `Workbench\App\Models\Task` ، کلیدهای `my.calendar` و `tasks_only`.

## پیش‌نیازها

- پکیج نصب شده و `ScopeResolver` بسته شده ([quickstart.md](../quickstart.md))
- Livewire در layout بارگذاری شده

---

## مرحله ۱: Migration

فایل: `database/migrations/xxxx_create_tasks_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 191)->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('location', 191)->nullable();
            $table->string('status', 24)->default('open');
            $table->dateTime('scheduled_for');
            $table->boolean('all_day')->default(false);
            $table->boolean('movable')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['scope_key', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
```

```bash
php artisan migrate
```

---

## مرحله ۲: مدل Eloquent

فایل: `app/Models/Task.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarModelDefinition;

class Task extends Model
{
    use HasCalendarEvents;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'all_day' => 'boolean',
            'movable' => 'boolean',
            'version' => 'integer',
        ];
    }

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'tasks',
            label: ['fa' => 'وظایف', 'en' => 'Tasks'],
            color: 'blue',
            startField: 'scheduled_for',
            endField: null,
            defaultDurationMinutes: 60,
            scopeField: 'scope_key',
            searchableFields: ['title', 'description', 'location'],
            statusOptions: [
                'open' => 'Open',
                'done' => 'Done',
                'locked' => 'Locked',
            ],
            statusColors: [
                'open' => 'blue',
                'done' => 'green',
                'locked' => 'rose',
            ],
            capabilitiesResolver: static function (self $task, CalendarContext $ctx, ActionCapabilities $base): ActionCapabilities {
                if ($task->status === 'locked' || ! $task->movable) {
                    return new ActionCapabilities(true, false, false, false, false);
                }

                return $base;
            },
        );
    }
}
```

| فیلد تعریف | رفتار |
| --- | --- |
| `endField: null` | پایان رویداد = شروع + `defaultDurationMinutes` |
| `scopeField` | هر کوئری/نوشتن با `WHERE scope_key = $context->scopeKey` |
| `capabilitiesResolver` | `locked` یا `movable=false` → فقط مشاهده در UI |

---

## مرحله ۳: ثبت مدل و Factory

`app/Providers/CalendarServiceProvider.php` (میزبان):

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Calendars\TasksCalendarFactory;
use App\Models\Task;
use Illuminate\Support\ServiceProvider;
use Karnoweb\Calendar\Support\CalendarManager;

final class CalendarServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $manager = $this->app->make(CalendarManager::class);
        $manager->registerModel('tasks', Task::class);
        $manager->register('my.calendar', TasksCalendarFactory::class);
        $manager->register('tasks_only', TasksCalendarFactory::class);
    }
}
```

`app/Calendars/TasksCalendarFactory.php`:

```php
<?php

declare(strict_types=1);

namespace App\Calendars;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Support\CalendarBuilder;

final class TasksCalendarFactory implements CalendarFactory
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

Provider را در `bootstrap/providers.php` یا `config/app.php` ثبت کنید.

---

## مرحله ۴: نوشتن `scope_key` هنگام ایجاد وظیفه

هنگام ایجاد Task در اپلیکیشن (Controller / Action میزبان):

```php
use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Data\CalendarContext;

$ctx = app(ScopeResolver::class)->resolve('my.calendar');

Task::create([
    'scope_key' => $ctx->scopeKey,
    'title' => 'Review sprint',
    'status' => 'open',
    'scheduled_for' => now()->addDay()->setTime(10, 0),
    'movable' => true,
    'version' => 1,
]);
```

اگر `scope_key` با resolver نخواند، رویداد در تقویم ظاهر نمی‌شود (فیلتر در SQL است، نه در Blade).

---

## مرحله ۵: نمایش UI

```blade
<div class="h-[calc(100dvh-4rem)]">
    <livewire:calendar::calendar
        calendar-key="my.calendar"
        layout="full"
        :preset-filters="['status' => 'open']"
    />
</div>
```

`:preset-filters` فقط وقتی اثر دارد که فیلتر `status` از `statusOptions` در `CalendarModelDefinition` ساخته شده باشد.

ویجت داشبورد (فقط ماه، بدون URL):

```blade
<div style="height: 360px">
    <livewire:calendar::calendar
        calendar-key="tasks_only"
        layout="compact"
        :url-sync="false"
        :views="['month']"
        locale="fa"
        system="jalali"
    />
</div>
```

---

## مرحله ۶: اجرا و بررسی نتیجه

**Workbench (مخزن پکیج):**

```bash
php vendor/bin/testbench serve --port=8765
```

- `http://127.0.0.1:8765/calendar/my-calendar` — کلید `my.calendar`
- `http://127.0.0.1:8765/calendar/tasks` — کلید `tasks_only`

**تست PHPUnit مرتبط:**

```bash
vendor/bin/phpunit --filter DomainCalendarAcceptanceTest
vendor/bin/phpunit tests/Scenarios/Scenario27CreateEventTest.php
```

انتظار: کوئری فقط به `workbench_tasks` (یا `tasks` شما) با `scope_key` actor فعلی؛ drag موفق `scheduled_for` و `version++` می‌کند؛ وضعیت `locked` drag را رد می‌کند.

---

## مرحله ۷: خطاهای محتمل

| خطا / رفتار | علت | راه‌حل |
| --- | --- | --- |
| `Conflict` بعد از drag | `version` با DB یکی نیست | رفرش تقویم؛ از optimistic locking در APIهای خودتان هم استفاده کنید |
| فیلتر status کار نمی‌کند | `statusOptions` در definition خالی | همان فیلد را در `calendarDefinition()` پر کنید |
| Provider سفارشی لازم است | تکرار RRULE روی جدول جدا | workbench از `DemoTaskProvider` استفاده می‌کند؛ برای Eloquent ساده همین trait کافی است |

## مسیر Provider/Handler سفارشی (اختیاری)

تقویم `personal` در workbench به‌جای `modelSources` از `DemoTaskProvider` + `DemoTaskHandler` استفاده می‌کند (سری تکرارشوندهٔ جدا). برای Task Manager معمولی **نیازی نیست** — `modelSources(['tasks'])` کافی است.

مرجع کد workbench:

- `workbench/app/Models/Task.php`
- `workbench/app/Calendars/TasksOnlyCalendarFactory.php`
- `workbench/app/Calendars/EnterpriseSampleCalendarFactory.php` (شاخه `my.calendar`)
