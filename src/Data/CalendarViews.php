<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;

/**
 * Names of Blade views used for replaceable parts. View NAMES only, never HTML.
 * Every override receives the same variables as the default (see docs/STYLING-AND-VIEWS.md).
 */
final readonly class CalendarViews
{
    public function __construct(
        public string $event = 'calendar::calendar.event',
        public string $eventPopover = 'calendar::calendar.event-popover',
        public string $eventDetails = 'calendar::calendar.event-details-drawer',
        public string $eventForm = 'calendar::calendar.event-form',
        public string $empty = 'calendar::calendar.empty',
    ) {
        foreach ($this->all() as $name) {
            if (! preg_match('/^[A-Za-z0-9_.:-]{1,160}$/', $name)) {
                throw new InvalidArgumentException('Invalid view name.');
            }
        }
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return [
            'event' => $this->event,
            'eventPopover' => $this->eventPopover,
            'eventDetails' => $this->eventDetails,
            'eventForm' => $this->eventForm,
            'empty' => $this->empty,
        ];
    }
}
