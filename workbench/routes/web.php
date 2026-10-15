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

    Route::get('/calendar/personal', fn () => view('workbench::calendar.personal'));
    Route::get('/calendar/dashboard', fn () => view('workbench::calendar.dashboard'));
    Route::get('/calendar/operations', fn () => view('workbench::calendar.operations'));
    Route::get('/calendar/public', fn () => view('workbench::calendar.public'));
});
