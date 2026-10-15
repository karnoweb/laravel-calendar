<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use Karnoweb\Calendar\Contracts\TimezoneResolver;
use Karnoweb\Calendar\Data\CalendarContext;

/** Host decision: the viewer's timezone is a server-side session value, validated against an allow-list. */
final class SessionTimezoneResolver implements TimezoneResolver
{
    private const ALLOWED = ['Asia/Tehran', 'Europe/Bucharest', 'UTC'];

    public function resolve(CalendarContext $context): string
    {
        $tz = (string) session('demo_timezone', config('calendar.timezone', 'UTC'));

        return in_array($tz, self::ALLOWED, true) ? $tz : (string) config('calendar.timezone', 'UTC');
    }
}
