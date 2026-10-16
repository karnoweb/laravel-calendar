<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Host routes (the calendar package registers none).
Route::middleware('web')->group(function (): void {
    Route::get('/workbench/health', fn () => response()->json(['ok' => true]));

    // Fake login for local/e2e use only: sets the server-side actor/tenant the scope resolver reads.
    Route::get('/workbench/login/{actor}/{tenant}', function (Request $request, string $actor, string $tenant) {
        abort_unless(ctype_digit($actor) && ctype_digit($tenant) && strlen($actor) <= 6 && strlen($tenant) <= 6, 404);
        $request->session()->put(['demo_actor' => $actor, 'demo_tenant' => $tenant]);
        if ($tz = $request->query('tz')) {
            abort_unless(in_array($tz, timezone_identifiers_list(), true), 422);
            $request->session()->put('demo_timezone', $tz);
        }
        if (($google = $request->query('google')) !== null) {
            abort_unless(in_array($google, ['fake', 'live', 'off'], true), 422);
            $google === 'off' ? $request->session()->forget('demo_google') : $request->session()->put('demo_google', $google);
        }
        if ($locale = $request->query('locale')) {
            abort_unless(in_array($locale, ['en', 'fa'], true), 422);
            $request->session()->put('demo_locale', $locale);
        }

        return redirect('/calendar/personal');
    });

    Route::get('/workbench/logout', function (Request $request) {
        $request->session()->flush();

        return redirect('/calendar/public');
    });

    Route::get('/calendar/personal', function (Request $request) {
        if (! $request->session()->has('demo_actor')) {
            $request->session()->put(['demo_actor' => '11', 'demo_tenant' => '101']);
        }
        if (! $request->session()->has('demo_features')) {
            $request->session()->put('demo_features', array_replace(\Karnoweb\Calendar\Data\CalendarSettings::FEATURES, ['confirmMove' => false]));
        }
        if ($locale = $request->query('locale')) {
            abort_unless(in_array($locale, ['en', 'fa'], true), 422);
            $request->session()->put('demo_locale', $locale);
        }

        return view('workbench::calendar.personal');
    });

    Route::get('/calendar/dashboard', function (Request $request) {
        if (! $request->session()->has('demo_actor')) {
            $request->session()->put(['demo_actor' => '11', 'demo_tenant' => '101']);
        }
        if (! $request->session()->has('demo_google')) {
            $request->session()->put('demo_google', 'fake');
        }
        if (! $request->session()->has('demo_features')) {
            $request->session()->put('demo_features', array_replace(\Karnoweb\Calendar\Data\CalendarSettings::FEATURES, ['confirmMove' => false]));
        }
        if ($locale = $request->query('locale')) {
            abort_unless(in_array($locale, ['en', 'fa'], true), 422);
            $request->session()->put('demo_locale', $locale);
        } elseif (! $request->session()->has('demo_locale')) {
            $request->session()->put('demo_locale', 'fa');
        }

        return view('workbench::calendar.dashboard');
    });
    Route::get('/calendar/operations', fn () => view('workbench::calendar.operations'));
    Route::get('/calendar/public', fn () => view('workbench::calendar.public'));
    Route::get('/calendar/comprehensive', function (Request $request) {
        if (! $request->session()->has('demo_actor')) {
            $request->session()->put(['demo_actor' => '11', 'demo_tenant' => '101']);
        }

        return view('workbench::calendar.scoped', ['calendarKey' => 'comprehensive', 'title' => 'تقویم جامع (Tasks + Classes + Meetings)']);
    });
    Route::get('/calendar/tasks', function (Request $request) {
        if (! $request->session()->has('demo_actor')) {
            $request->session()->put(['demo_actor' => '11', 'demo_tenant' => '101']);
        }

        return view('workbench::calendar.scoped', ['calendarKey' => 'tasks_only', 'title' => 'تقویم وظایف (فقط Tasks)']);
    });
    Route::get('/calendar/classes', function (Request $request) {
        if (! $request->session()->has('demo_actor')) {
            $request->session()->put(['demo_actor' => '11', 'demo_tenant' => '101']);
        }

        return view('workbench::calendar.scoped', ['calendarKey' => 'classes_only', 'title' => 'تقویم آموزشی (فقط Classes)']);
    });

    Route::match(['get', 'post'], '/workbench/settings', function (Request $request) {
        $defaults = \Karnoweb\Calendar\Data\CalendarSettings::FEATURES;
        $features = array_replace($defaults, (array) $request->session()->get('demo_features', []));

        $action = (string) $request->input('action', '');
        if ($action === 'reset') {
            $features = array_replace($defaults, ['confirmMove' => false]);
            $request->session()->put('demo_features', $features);
            $request->session()->put('demo_theme', 'luxe');
        } elseif ($action === 'reseed') {
            app(\Workbench\Database\Seeders\CalendarDemoSeeder::class)->run();
        }

        if ($request->has('feature')) {
            $key = (string) $request->input('feature');
            if (array_key_exists($key, $defaults)) {
                $val = $request->has('enabled')
                    ? filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN)
                    : ! ($features[$key] ?? true);
                $features[$key] = $val;
                $request->session()->put('demo_features', $features);
            }
        }

        if ($request->has('theme')) {
            $theme = (string) $request->input('theme');
            if (in_array($theme, ['luxe', 'dark', 'light', 'auto'], true)) {
                $request->session()->put('demo_theme', $theme);
            }
        }

        if ($request->has('locale')) {
            $locale = (string) $request->input('locale');
            if (in_array($locale, ['fa', 'en'], true)) {
                $request->session()->put('demo_locale', $locale);
            }
        }

        if ($request->has('google')) {
            $google = (string) $request->input('google');
            if ($google === 'off' || $google === '0' || $google === '') {
                $request->session()->forget('demo_google');
            } elseif (in_array($google, ['fake', 'live'], true)) {
                $request->session()->put('demo_google', $google);
            }
        }

        if ($request->has('actor')) {
            $actor = (string) $request->input('actor');
            if ($actor === 'guest') {
                $request->session()->forget(['demo_actor', 'demo_tenant']);
            } elseif (ctype_digit($actor) && strlen($actor) <= 6) {
                $request->session()->put(['demo_actor' => $actor, 'demo_tenant' => (string) $request->input('tenant', '101')]);
            }
        }

        $state = [
            'ok' => true,
            'features' => array_replace($defaults, (array) $request->session()->get('demo_features', [])),
            'theme' => (string) $request->session()->get('demo_theme', 'luxe'),
            'locale' => (string) $request->session()->get('demo_locale', 'fa'),
            'google' => (bool) $request->session()->get('demo_google'),
            'actor' => (string) $request->session()->get('demo_actor', '11'),
        ];

        if ($request->expectsJson() || $request->boolean('json')) {
            return response()->json($state);
        }

        return redirect()->back();
    });
});
