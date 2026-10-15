<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;

/**
 * Default resolver: fail closed. Guests are rejected; an authenticated user gets a private,
 * per-user scope. Hosts replace this binding to inject tenant/branch/module scope.
 */
final class DenyAnonymousScopeResolver implements ScopeResolver
{
    public function resolve(string $calendarKey): CalendarContext
    {
        $id = Auth::id();
        if ($id === null || $id === '') {
            throw new CalendarAccessDenied('Authentication required.');
        }

        return new CalendarContext(
            calendarKey: $calendarKey,
            actorId: (string) $id,
            scopeKey: 'actor:'.$id,
            locale: app()->getLocale(),
            timezone: (string) config('calendar.timezone', config('app.timezone', 'UTC')),
            instanceId: (string) Str::uuid(),
        );
    }
}
