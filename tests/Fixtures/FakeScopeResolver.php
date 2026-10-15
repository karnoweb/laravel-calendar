<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Fixtures;

use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;

/** Scope/actor come from FakeStore statics, standing in for a host-resolved session. A null actor is a guest. */
final class FakeScopeResolver implements ScopeResolver
{
    public function resolve(string $calendarKey): CalendarContext
    {
        if (FakeStore::$currentActor === null) {
            throw new CalendarAccessDenied('Authentication required.');
        }

        return new CalendarContext(
            $calendarKey, FakeStore::$currentActor, FakeStore::$currentScope,
            app()->getLocale(), 'Asia/Tehran', 'test-instance',
        );
    }
}
