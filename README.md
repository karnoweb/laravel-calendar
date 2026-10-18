# karnoweb/laravel-calendar

Calendar engine and Livewire UI for Laravel. Month, week, day and list views. Gregorian and Jalali. RTL and LTR. Drag, resize, recurrence, several event sources, and an optional Google Calendar source.

The package does not ship routes, migrations, or domain models. The host application owns the records, the scope of each viewer, and Google OAuth.

MIT license.

## Requirements

- PHP 8.3+
- Laravel 13 (`illuminate/support` ^13, `illuminate/contracts` ^13)
- Livewire 4
- `morilog/jalali` and `rlanvin/php-rrule` are installed with the package

## Installation

```bash
composer require karnoweb/laravel-calendar
```

`Karnoweb\Calendar\CalendarServiceProvider` is auto-discovered. Styles and scripts are inlined by Livewire. Publish only what you need to override:

```bash
php artisan vendor:publish --tag=calendar-config
php artisan vendor:publish --tag=calendar-assets
php artisan vendor:publish --tag=calendar-views
php artisan vendor:publish --tag=calendar-lang
```

`config/calendar.php` uses `assets.mode = inline` by default. `published` expects the files from `calendar-assets` under `public/vendor/calendar`.

## Host integration

The browser does not choose scope, sources, or timezone. Bind a scope resolver in a service provider. Anonymous read is denied unless a calendar explicitly allows it.

```php
use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;

final class MyScopeResolver implements ScopeResolver
{
    public function resolve(string $calendarKey): CalendarContext
    {
        $user = auth()->user() ?? throw new CalendarAccessDenied;

        return new CalendarContext(
            $calendarKey,
            (string) $user->id,
            "user:{$user->id}",
            app()->getLocale(),
            config('app.timezone'),
            $calendarKey,
        );
    }
}

$this->app->singleton(ScopeResolver::class, MyScopeResolver::class);
```

Register one factory per calendar key. The factory is resolved again on every Livewire request. Register the class, not a closure.

```php
use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Support\CalendarBuilder;

final class PersonalCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->sources(['tasks' => ['provider' => TaskProvider::class, 'label' => 'Tasks', 'color' => 'blue']])
            ->actions(TaskHandler::class)
            ->settings(new CalendarSettings)
            ->build();
    }
}

app(\Karnoweb\Calendar\Support\CalendarManager::class)->register('personal', PersonalCalendarFactory::class);
```

`EventProvider` reads a page of events for a range. `ActionHandler` applies create, update, move, resize, and delete. Both must keep the query and the write inside `$context->scopeKey`.

```blade
<livewire:calendar::calendar calendar-key="personal" />
<livewire:calendar::full-calendar calendar-key="personal" />
<livewire:calendar::mini-month calendar-key="personal" />
<livewire:calendar::upcoming-agenda calendar-key="personal" :limit="8" />
```

Bind `TimezoneResolver` when the viewer timezone is not `config('calendar.timezone')`.

## Eloquent models

`Karnoweb\Calendar\Concerns\HasCalendarEvents` is optional. The trait does not register a source. The host calls `Calendar::registerModel()`. Column names come from `CalendarModelDefinition` (`startField`, `endField`, `scopeField`, and the rest). There is no required `starts_at` or `user_id`.

Recurrence on a model is stored only when `recurrenceRuleField` and `recurrenceExceptionsField` are set. One occurrence can be replaced without changing the series title. See [docs/MODEL-TRAIT.md](docs/MODEL-TRAIT.md).

## Google Calendar

The host performs OAuth and token refresh. The package asks `GoogleAccessTokenResolver` for an access token of the current actor.

Read-only scope: `https://www.googleapis.com/auth/calendar.readonly`. Read and write: `https://www.googleapis.com/auth/calendar.events`.

Add the source with `Karnoweb\Calendar\Google\GoogleCalendarProvider`. Writes stay off until `CALENDAR_GOOGLE_WRITABLE=true` and the host handler delegates the `google` source to `GoogleCalendarHandler`. A missing token leaves that source empty. A stale ETag is a conflict. Series editing and creating a recurrence rule in Google are not supported. Config lives under `google` in `config/calendar.php`: `calendar_id`, `writable`, `timeout`.

## Security

Scope, sources, filters, and timezone are resolved on the server. Event identity is `(sourceKey, eventId, occurrenceKey)`. Text is escaped, URLs are scheme-allow-listed, and colours are named tokens or hex. A write goes through rate limit, package policy, the idempotency journal, a fresh provider read, validation, then the host handler. An unexpected handler failure is an unknown outcome, not a success.

## Documentation

| Topic | Document |
| --- | --- |
| Install and publish tags | [docs/INSTALLATION.md](docs/INSTALLATION.md) |
| Full integration (FA) | [docs/quickstart.md](docs/quickstart.md) |
| Configuration | [docs/CONFIGURATION.md](docs/CONFIGURATION.md) |
| Public API | [docs/api/public-api.md](docs/api/public-api.md) |
| Example scenarios | [docs/examples/README.md](docs/examples/README.md) |
| Model trait | [docs/MODEL-TRAIT.md](docs/MODEL-TRAIT.md) |
| Event sources | [docs/EVENT-SOURCES.md](docs/EVENT-SOURCES.md) |
| Queries | [docs/QUERIES-AND-PERFORMANCE.md](docs/QUERIES-AND-PERFORMANCE.md) |
| Views and drawer | [docs/VIEWS-AND-DRAWER.md](docs/VIEWS-AND-DRAWER.md) |
| Integrations | [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md) |
| Troubleshooting | [docs/troubleshooting.md](docs/troubleshooting.md) |
| Upgrade | [docs/UPGRADE.md](docs/UPGRADE.md) |
| Persian quick start | [docs/QUICKSTART-FA.md](docs/QUICKSTART-FA.md) |
| Persian usage | [docs/USAGE-FA.md](docs/USAGE-FA.md) |
| Tests, demo, screenshots | [README_Test.md](README_Test.md) |

## License

MIT.
