<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;

/**
 * Host scope resolver: actor and tenant come from the server-side session (set by the fake login route),
 * never from the browser's calendar parameters.
 *
 *  - "public"      : guests allowed, scope "public"
 *  - "personal"    : scope t{tenant}:u{actor}
 *  - "operations"  : scope t{tenant}
 */
final class SecureDemoScopeResolver implements ScopeResolver
{
    public function resolve(string $calendarKey): CalendarContext
    {
        $actor = session('demo_actor');
        $tenant = session('demo_tenant');
        $tz = (string) session('demo_timezone', config('calendar.timezone', 'UTC'));
        app()->setLocale((string) session('demo_locale', app()->getLocale()));

        if ($calendarKey === 'public') {
            return new CalendarContext($calendarKey, $actor === null ? null : (string) $actor, 'public', app()->getLocale(), $tz, 'public');
        }
        if ($actor === null || $tenant === null) {
            throw new CalendarAccessDenied('Login required.');
        }

        $scope = $calendarKey === 'operations' ? "t{$tenant}" : "t{$tenant}:u{$actor}";

        return new CalendarContext($calendarKey, (string) $actor, $scope, app()->getLocale(), $tz, $calendarKey);
    }
}
