# Model Trait Integration (`HasCalendarEvents`)

This guide explains how Eloquent models integrate with `karnoweb/laravel-calendar` using `HasCalendarEvents` and `CalendarModelDefinition` without writing custom `EventProvider` or `ActionHandler` classes.

---

## 1. Quick Start

### Step 1: Add `HasCalendarEvents` to your Eloquent Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarModelDefinition;

final class Task extends Model
{
    use HasCalendarEvents;

    protected $fillable = [
        'scope_key',
        'title',
        'description',
        'status',
        'category',
        'scheduled_for',
        'all_day',
        'movable',
        'version',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'all_day' => 'boolean',
        'movable' => 'boolean',
        'version' => 'integer',
    ];

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return CalendarModelDefinition::make('tasks')
            ->label(['fa' => 'تسک‌ها', 'en' => 'Tasks'])
            ->color('blue')
            ->startField('scheduled_for')
            ->endField(null)
            ->defaultDurationMinutes(60)
            ->allDayField('all_day')
            ->titleField('title')
            ->descriptionField('description')
            ->statusField('status')
            ->categoryField('category')
            ->movableField('movable')
            ->versionField('version')
            ->scopeField('scope_key')
            ->searchable(['title', 'description', 'category'])
            ->statusOptions([
                'todo' => ['fa' => 'در انتظار', 'en' => 'To Do'],
                'in_progress' => ['fa' => 'در حال انجام', 'en' => 'In Progress'],
                'done' => ['fa' => 'تکمیل شده', 'en' => 'Done'],
            ])
            ->statusColors([
                'todo' => 'amber',
                'in_progress' => 'blue',
                'done' => 'emerald',
            ])
            ->capabilities(ActionCapabilities::all(recurrence: false));
    }
}
```

### Step 2: Register Models in `AppServiceProvider::boot()`

```php
use App\Models\EducationalClass;
use App\Models\Meeting;
use App\Models\Task;
use Karnoweb\Calendar\Facades\Calendar;

public function boot(): void
{
    Calendar::registerModels([
        Task::class,
        EducationalClass::class,
        Meeting::class,
    ]);
}
```

### Step 3: Build Calendars with `modelSources()`

```php
use Karnoweb\Calendar\Support\CalendarBuilder;

return (new CalendarBuilder)
    ->key('main')
    ->modelSources(['tasks', 'classes', 'meetings'])
    ->holidays(true)
    ->build();
```

---

## 2. Supporting Diverse Database Schemas

Models do not need identical column names:

| Scenario | Configuration | Behavior |
|---|---|---|
| Single timestamp (`scheduled_for`) | `->startField('scheduled_for')->endField(null)->defaultDurationMinutes(60)` | Timed events receive a synthetic end (`start + 60m`); all-day events span 1 day; resizing is automatically disabled. |
| Start & End timestamps (`starts_at`, `ends_at`) | `->startField('starts_at')->endField('ends_at')` | Multi-day and timed ranges are queried and persisted directly. Nullable `ends_at` falls back to `start + defaultDurationMinutes`. |
| Computed title | `->titleField(fn (EducationalClass $c) => $c->title.' — '.$c->instructor_name)` | Supports closures while keeping database columns searchable via `->searchable([...])`. |
| Custom form fields | `->formFields([...])` | Renders model-specific inputs in the Create/Edit modal and persists them automatically. |

---

## 3. Ownership, Scope & Authorization

`EloquentEventProvider` is **fail-closed** by default. Every model must declare at least one scope mechanism:

- `->scopeField('scope_key')` — matches `CalendarContext::$scopeKey`.
- `->ownerField('user_id')` — matches `CalendarContext::$actorId`.
- `->tenantField('tenant_id')` — matches `CalendarContext::$tenantId`.
- `->readScope(fn (Builder $q, CalendarContext $ctx) => ...)` — custom Eloquent query scope.
- `->publicRead(true)` — explicitly opts into unrestricted read access.

Per-record capabilities and authorization can be customized via:

```php
->capabilitiesUsing(function (Task $task, CalendarContext $ctx): ActionCapabilities {
    if ($task->status === 'locked' || ! $task->movable) {
        return ActionCapabilities::none();
    }

    return ActionCapabilities::all(recurrence: false);
})
->authorizeUsing(function (Task $task, CalendarAction $action, CalendarContext $ctx): bool {
    return $task->status !== 'locked';
})
```

---

## 4. Custom Write Mapping & Domain Events

- Use `->writeUsing(fn (Model $record, ActionCommand $cmd, CalendarContext $ctx, array $attributes): array => ...)` to customize attribute mapping before save.
- Every committed create, update, move, resize, or delete dispatches `Karnoweb\Calendar\Events\CalendarEventMutated` with `$calendarKey`, `$sourceKey`, `$action`, `$reference`, `$actorId`, `$tenantId`, `$scopeKey`, and `$operationId`.
