# Per-Calendar Source Selection & Multi-Calendar Isolation

`karnoweb/laravel-calendar` supports registering multiple Eloquent models globally while restricting each calendar definition or embedded Livewire component to an explicit subset of sources.

---

## 1. Two Levels of Source Selection

### Level 1: Calendar Definition (`CalendarBuilder`)

Define dedicated calendars with their own allowed sources:

```php
// 1. Comprehensive calendar with all model sources
(new CalendarBuilder)
    ->key('comprehensive')
    ->modelSources() // Attaches all registered models
    ->holidays(true)
    ->build();

// 2. Tasks-only calendar
(new CalendarBuilder)
    ->key('tasks_only')
    ->modelSources(['tasks'])
    ->build();

// 3. Classes-only calendar
(new CalendarBuilder)
    ->key('classes_only')
    ->modelSources(['classes'])
    ->build();
```

### Level 2: Component Embed Narrowing (`sources` Prop)

Narrow a multi-source calendar on a specific Livewire component instance without creating a separate factory:

```blade
{{-- Full calendar with all sources --}}
<livewire:karnoweb-calendar:full-calendar
    calendar-key="comprehensive"
    wire:key="cal-full"
/>

{{-- Mini month restricted strictly to classes --}}
<livewire:karnoweb-calendar:mini-month
    calendar-key="comprehensive"
    :sources="['classes']"
    wire:key="cal-mini-classes"
/>

{{-- Upcoming agenda restricted strictly to tasks --}}
<livewire:karnoweb-calendar:upcoming-agenda
    calendar-key="comprehensive"
    :sources="['tasks']"
    :agenda-limit="5"
    wire:key="cal-agenda-tasks"
/>
```

---

## 2. Server-Side Security Guarantees

Source restrictions are enforced on the **server** via `EmbedOptions::apply()` and `CalendarDefinition`:

1. **Zero Unselected Queries**: `ReadDispatcher` only iterates over `$definition->sourceKeys()` intersected with `$request->enabledSources`. Unselected model tables are never queried.
2. **Filter & Search Isolation**: Only filters belonging to the calendar's active sources are exposed or accepted by `FilterValidator::sanitize()`.
3. **Write Protection**: Creating, updating, moving, resizing, or deleting a record belonging to an unselected source is rejected by `CalendarAuthorizationGuard` and `EventReferenceGuard` (`ActionStatus::Forbidden` / `CalendarAccessDenied`).
4. **Fail-Closed Empty Sources**: Passing `:sources="[]"` or an unknown source key such as `:sources="['invalid']"` throws `CalendarAccessDenied` and never widens to all sources.
5. **Automatic UI Adaptation**:
   - Single-source calendars hide redundant source toggle chips in the toolbar/sidebar.
   - When only one writable source exists, the Create Event modal automatically selects that source and hides the source dropdown.
