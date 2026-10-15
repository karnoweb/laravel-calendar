<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Providers;

use Karnoweb\Calendar\Contracts\TimezoneResolver;
use Karnoweb\Calendar\Data\CalendarContext;

final class AppTimezoneResolver implements TimezoneResolver
{
    public function resolve(CalendarContext $context): string
    {
        $tz = (string) config('calendar.timezone', config('app.timezone', 'UTC'));

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }
}
