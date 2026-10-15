# karnoweb/laravel-calendar

Google-Calendar-style calendar for Laravel 13 + Livewire 4: Month / Week / Day / List views, Gregorian and Jalali (Persian) calendars, RTL/LTR, drag/resize (mouse, touch, keyboard), recurring events, multiple event sources, optional Google Calendar source.

The package ships **no routes, migrations or Eloquent models**. Your application owns the data (event providers), the writes (an action handler), the access rules (scope resolver) and, for Google, OAuth.

> **Docs (فارسی):** [شروع سریع](docs/QUICKSTART-FA.md) · [راهنمای استفاده](docs/USAGE-FA.md) — embed/dashboard, full-height layout, views/systems/sources switches, holidays, Google, locale.

## Requirements

- PHP 8.3+, Laravel 13, Livewire 4
- Jalali support uses `morilog/jalali` (installed with the package)

## Install

```bash
composer require karnoweb/laravel-calendar
```

The service provider is auto-discovered. CSS/JS are inlined by Livewire (`@assets`), nothing to publish. Optional:

```bash
php artisan vendor:publish --tag=calendar-config   # config/calendar.php
```

## Quick start (host application)

1. **Scope resolver** — decides *who* is looking and *which data* they may see. The browser never chooses scope, sources or timezone.

```php
use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;

final class MyScopeResolver implements ScopeResolver
{
    public function resolve(string $calendarKey): CalendarContext
    {
        $user = auth()->user() ?? throw new CalendarAccessDenied;

        return new CalendarContext($calendarKey, (string) $user->id, "user:{$user->id}", app()->getLocale(), config('app.timezone'), $calendarKey);
    }
}

$this->app->singleton(ScopeResolver::class, MyScopeResolver::class); // in a service provider
```

Anonymous read is denied by default.

2. **Provider** (read) and **handler** (write):

```php
use Karnoweb\Calendar\Contracts\EventProvider;   // fetch(EventQuery, CalendarContext): EventPage; find(EventReference, CalendarContext): ?CalendarEventData
use Karnoweb\Calendar\Contracts\ActionHandler;   // execute(ActionCommand, CalendarContext): ActionResult
```

Always filter by `$context->scopeKey` inside the provider and handler. See `workbench/app/Calendar/` for a complete, tested example (`DemoTaskProvider`, `DemoTaskHandler`).

3. **Calendar factory** — declares sources, handler, filters, settings. Registered by class name (never a closure), and re-resolved on every Livewire request.

```php
use Karnoweb\Calendar\Contracts\CalendarFactory;
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

// boot(): 
app(\Karnoweb\Calendar\Support\CalendarManager::class)->register('personal', PersonalCalendarFactory::class);
```

4. **Render**:

```blade
<livewire:calendar::calendar calendar-key="personal" />
```

Optional: bind a `TimezoneResolver` if the viewer's timezone is not `config('calendar.timezone')`.

## Google Calendar

The package can show (and optionally edit) a Google Calendar next to your own sources. **Your app does OAuth**; the package only needs a valid access token for the current user, server-side.

1. Do the Google OAuth flow in your app (e.g. Socialite) with scope
   `https://www.googleapis.com/auth/calendar.readonly` (read only) or `https://www.googleapis.com/auth/calendar.events` (read + write). Store and refresh the tokens yourself.
2. Bind the token resolver:

```php
use Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver;

final class MyGoogleTokens implements GoogleAccessTokenResolver
{
    public function accessToken(CalendarContext $context): ?string
    {
        return GoogleAccount::where('user_id', $context->actorId)->first()?->freshAccessToken(); // null = not connected
    }
}

$this->app->bind(GoogleAccessTokenResolver::class, MyGoogleTokens::class);
```

3. Add the source in your calendar factory:

```php
->sources([
    'tasks'  => [...],
    'google' => ['provider' => \Karnoweb\Calendar\Google\GoogleCalendarProvider::class, 'label' => 'Google Calendar', 'color' => '#1a73e8'],
])
```

4. Writes are **off** by default. To allow create/edit/move/resize/delete on Google events:
   set `CALENDAR_GOOGLE_WRITABLE=true` and route the `google` source in your action handler:

```php
if ($command->targetSourceKey() === 'google') {
    return app(\Karnoweb\Calendar\Google\GoogleCalendarHandler::class)->execute($command, $context);
}
```

Config (`config/calendar.php` → `google`): `calendar_id` (default `primary`), `writable`, `timeout`.

Behaviour and limits:
- Not connected (resolver returns `null`) → the source is simply empty.
- Recurring Google events are expanded by Google (`singleEvents=true`); each instance is edited on its own. Editing a whole series or creating recurring events in Google is not supported.
- Optimistic concurrency uses the Google ETag (`If-Match`): a stale edit becomes a conflict, not an overwrite.
- Tokens, calendar id and API base URL are server-side only. Error messages never include Google response bodies.
- API errors: 401/403 → forbidden, 404/410 → not found, 429/5xx → retryable.

## Run the demo (workbench)

A demo host app lives in `workbench/` (personal, operations and public calendars, seeded data).

```bash
composer install
npm install && npm run build        # only needed after changing resources/css or resources/js

php vendor/bin/testbench migrate:fresh   # creates workbench/database/demo.sqlite and seeds it (testbench.yaml seeds automatically)
php vendor/bin/testbench serve --port=8765
```

> Don't also pass `--seed`/`db:seed`: `testbench.yaml` already seeds, you would get every row twice.

Open (the "login" routes are demo-only and set the server-side actor/tenant/timezone/locale):

| What | URL |
| --- | --- |
| Personal calendar (actor 11, tenant 101) | `http://127.0.0.1:8765/workbench/login/11/101?tz=Asia/Tehran&locale=en` |
| Persian / Jalali / RTL | `http://127.0.0.1:8765/workbench/login/11/101?locale=fa` |
| With a fake Google source | add `&google=fake` (in-process stand-in for the Google API, no credentials; writes are enabled) |
| With a real Google token | set env `WORKBENCH_GOOGLE_TOKEN=<access token>` for the server, then add `&google=live` |
| Turn Google off | add `&google=off` |
| Operations calendar (tenant-wide) | `http://127.0.0.1:8765/calendar/operations` |
| Public read-only calendar | `http://127.0.0.1:8765/workbench/logout` |

Other actors for permission checks: actor 22 (other user), tenant 202 (other tenant), actor 33 (cannot delete).

## Build assets

```bash
npm run build     # esbuild: resources/js + resources/css -> public/dist/calendar.{js,css}
```

`public/dist` is committed because the assets are inlined from there at runtime.

## Tests

```bash
php vendor/bin/phpunit                          # everything
php vendor/bin/phpunit --testsuite Scenarios    # numbered business scenarios (Scenarios/ScenarioNN*Test.php)
php vendor/bin/phpunit --filter Scenario25      # one scenario
```

PHPUnit is configured to fail on skipped, risky and warning tests. Browser (Playwright) tests are not written yet; behaviour of drag/touch/keyboard is currently verified only through the server calls they trigger.

## Security model (short)

- Scope, sources, filters and timezone are server-decided; browser input is validated and clamped.
- Every browser-callable method re-resolves the calendar and re-fetches the event through your provider before acting.
- Event identity is `(sourceKey, eventId, occurrenceKey)`, never parsed from prefixes.
- All text is escaped; URLs are scheme-allow-listed; colours are token/hex only.
- Writes pass: rate limit + payload cap → package write-denial → idempotency journal → reference + version check → validation → your handler. Unexpected handler failure is reported as *outcome unknown*, never as success.

## Known limitations

- The demo's idempotency journal is not exactly-once under all crash windows (`guaranteesExactlyOnce()` is `false`); a production host should implement `ActionExecutionJournal` on its own database.
- No real-browser test suite yet (Playwright), no Pint/PHPStan run recorded yet.
- Google: no series editing, no recurring creation, no multiple Google calendars per source (one calendar id from config).
