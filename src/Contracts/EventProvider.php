<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;

interface EventProvider
{
    public function fetch(EventQuery $query, CalendarContext $context): EventPage;

    public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData;
}
