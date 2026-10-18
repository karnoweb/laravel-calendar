<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use Karnoweb\Calendar\Contracts\EventDetailsSection;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventDetailsPage;

/** Host-owned related section. Calendar stores nothing about orders. */
final class OrderDetailsSection implements EventDetailsSection
{
    public function authorize(CalendarEventData $event, CalendarContext $context): bool
    {
        return $event->reference->sourceKey === 'orders' && $context->actorId !== null;
    }

    public function page(CalendarEventData $event, CalendarContext $context, int $page, int $limit): EventDetailsPage
    {
        return new EventDetailsPage([
            ['label' => 'Fulfillment', 'value' => 'Warehouse dispatch'],
        ]);
    }
}
