<?php

declare(strict_types=1);

namespace Workbench\App\Calendars;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Support\CalendarBuilder;
use Workbench\App\Calendar\DemoClassProvider;

/** Anonymous read-only calendar: requires BOTH the config switch and this explicit per-calendar opt-in. */
final class PublicCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->sources(['classes' => ['provider' => DemoClassProvider::class, 'label' => 'Public classes', 'color' => 'teal', 'readOnly' => true]])
            ->settings(new CalendarSettings(allowAnonymousRead: true, features: ['create' => false, 'edit' => false, 'delete' => false, 'move' => false, 'resize' => false]))
            ->build();
    }
}
