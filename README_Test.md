# Testing karnoweb/laravel-calendar

Commands for this repository. They are not part of the installed package.

## Automated tests

```bash
composer install
composer test
```

`composer test` runs `vendor/bin/phpunit`. PHPUnit fails the run on skipped, risky, and warning tests. The database is sqlite through Orchestra Testbench. `WorkbenchTestCase` refreshes the database.

Suites in `phpunit.xml`:

| Suite | Directory |
| --- | --- |
| Unit | `tests/Unit` |
| Feature | `tests/Feature` |
| Integration | `tests/Integration` |
| Security | `tests/Security` |
| Performance | `tests/Performance` |
| Scenarios | `tests/Scenarios` |

```bash
php vendor/bin/phpunit --testsuite Scenarios
php vendor/bin/phpunit --testsuite Integration
php vendor/bin/phpunit --filter Scenario25
php vendor/bin/phpunit --filter EloquentRecurrenceTest
```

Static checks:

```bash
composer analyse
composer lint
```

`composer analyse` is Larastan (`phpstan analyse --memory-limit=1G`) using `phpstan.neon`. `composer lint` is `pint --test` and does not rewrite files.

Scenario notes: [docs/TEST-SCENARIOS.md](docs/TEST-SCENARIOS.md).

`npm run test:browser` calls Playwright with `playwright.config.mjs`. That config file is not in the repository, so the script cannot run. Drag, touch, and keyboard behaviour is covered by the Livewire calls those gestures send.

## Demo server

`workbench/` is a host application used by Testbench and by hand. `testbench.yaml` migrates `workbench/database/migrations` and runs `CalendarDemoSeeder`. The sqlite file path in that yaml is absolute on this machine.

```bash
composer install
npm install
npm run build
php vendor/bin/testbench migrate:fresh
php vendor/bin/testbench serve --port=8765
```

`npm run build` is required after a change to `resources/js` or `resources/css`. It writes `public/dist/calendar.js` and `public/dist/calendar.css`. Those files are what the UI loads.

Do not pass `--seed` or `db:seed` as well. `testbench.yaml` already seeds, and the rows would be inserted twice.

| Page | URL |
| --- | --- |
| Personal, English, Tehran | `http://127.0.0.1:8765/workbench/login/11/101?tz=Asia/Tehran&locale=en` |
| Personal, Persian, RTL | `http://127.0.0.1:8765/workbench/login/11/101?locale=fa` |
| Fake Google API | add `&google=fake` |
| Live Google token | set `WORKBENCH_GOOGLE_TOKEN` on the server, then add `&google=live` |
| Google off | add `&google=off` |
| Dashboard | `http://127.0.0.1:8765/calendar/dashboard` |
| Operations | `http://127.0.0.1:8765/calendar/operations` |
| Public read-only | `http://127.0.0.1:8765/workbench/logout` |
| Comprehensive | `http://127.0.0.1:8765/calendar/comprehensive` |
| Tasks only | `http://127.0.0.1:8765/calendar/tasks` |
| Classes only | `http://127.0.0.1:8765/calendar/classes` |
| My calendar | `http://127.0.0.1:8765/calendar/my-calendar` |
| Education | `http://127.0.0.1:8765/calendar/education-calendar` |
| CRM | `http://127.0.0.1:8765/calendar/crm-calendar` |
| Delivery | `http://127.0.0.1:8765/calendar/delivery-calendar` |
| Overview | `http://127.0.0.1:8765/calendar/overview-calendar` |

Other actors: `22` is another user, tenant `202` is another tenant, actor `33` cannot delete.

Theme and locale for the session:

```text
http://127.0.0.1:8765/workbench/settings?theme=dark&locale=fa&json=1
```

`theme` is `light`, `dark`, `luxe`, or `auto`. `locale` is `fa` or `en`. `fa` is RTL. `en` is LTR.

## Review screenshots

Google Chrome must be installed. The demo server must already be listening on port 8765. Remote font hosts are aborted so a blocked font request does not stall the shot.

```bash
npm run shots -- fa light
npm run shots -- en dark desktop
npm run shots -- rtl dark mobile dashboard
npm run shots -- fa,en light,dark mobile personal,dashboard
```

Argument order:

| Position | Values | Default |
| --- | --- | --- |
| locale | `fa`, `rtl`, `en`, `ltr` | `fa,en` |
| theme | `light`, `dark` | `light,dark` |
| size | `desktop` (1440×900), `mobile` (390×844) | both |
| page | a name from the table below, commas allowed | all pages |

`fa` and `rtl` open the Persian RTL UI. `en` and `ltr` open the English LTR UI. Omitted arguments mean every value of that argument.

`--base` and `--out` still work when you call Node directly:

```bash
node scripts/review-shots.mjs --base=http://127.0.0.1:8765 --out=review-shots fa light desktop personal
```

`npm run` on Windows drops `--locale` and similar flags before the script sees them. Pass the four words above, not `--locale fa`.

Files land in `review-shots/` as `{page}-{locale}-{theme}-{size}.jpg`.

Page names: `personal`, `dashboard`, `operations`, `public`, `comprehensive`, `tasks`, `classes`, `my-calendar`, `education-calendar`, `crm-calendar`, `delivery-calendar`, `overview-calendar`.
