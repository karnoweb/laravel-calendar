<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\CalendarContext;

interface ScopeResolver
{
    public function resolve(string $calendarKey): CalendarContext;
}
