<?php

declare(strict_types=1);

namespace Workbench\App\Calendars;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Data\CalendarViews;
use Karnoweb\Calendar\Support\CalendarBuilder;
use Workbench\App\Calendar\DemoClassProvider;
use Workbench\App\Calendar\DemoReservationProvider;
use Workbench\App\Calendar\DemoShiftProvider;
use Workbench\App\Calendar\DemoTaskHandler;

/** Several providers in one calendar; classes/shifts are read-only, reservations may only move/resize. */
final class OperationsCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->sources([
                'classes' => ['provider' => DemoClassProvider::class, 'label' => 'Classes', 'color' => 'green', 'readOnly' => true],
                'shifts' => ['provider' => DemoShiftProvider::class, 'label' => 'Shifts', 'color' => 'orange', 'readOnly' => true],
                'reservations' => ['provider' => DemoReservationProvider::class, 'label' => 'Reservations', 'color' => 'purple'],
            ])
            ->actions(DemoTaskHandler::class)
            ->views(new CalendarViews(event: 'workbench::calendar.custom-card'))
            ->settings(new CalendarSettings(features: ['create' => false, 'edit' => false, 'delete' => false, 'recurrence' => false], styles: ['theme' => 'light']))
            ->build();
    }
}
