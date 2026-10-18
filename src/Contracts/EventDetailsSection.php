<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventDetailsPage;

interface EventDetailsSection
{
    public function authorize(CalendarEventData $event, CalendarContext $context): bool;

    public function page(CalendarEventData $event, CalendarContext $context, int $page, int $limit): EventDetailsPage;
}
