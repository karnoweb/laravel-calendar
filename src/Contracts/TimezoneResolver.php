<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\CalendarContext;

/** Server-side timezone decision. Browser supplied timezone strings are never trusted. */
interface TimezoneResolver
{
    public function resolve(CalendarContext $context): string;
}
