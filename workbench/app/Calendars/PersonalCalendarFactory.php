<?php

declare(strict_types=1);

namespace Workbench\App\Calendars;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarFilterData;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Support\CalendarBuilder;
use Workbench\App\Calendar\DemoTaskHandler;
use Workbench\App\Calendar\DemoTaskProvider;

final class PersonalCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->sources(['tasks' => ['provider' => DemoTaskProvider::class, 'label' => 'Tasks', 'color' => 'blue']])
            ->google((bool) session('demo_google'))
            ->holidays()
            ->actions(DemoTaskHandler::class)
            ->filters([new CalendarFilterData('tasks', 'status', 'Status', ['open' => 'Open', 'done' => 'Done', 'locked' => 'Locked'])])
            ->settings(new CalendarSettings(styles: ['theme' => 'luxe']))
            ->build();
    }
}
