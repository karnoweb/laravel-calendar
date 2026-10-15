<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Fixtures;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Support\CalendarBuilder;

final class FakeCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->sources(['tasks' => ['provider' => FakeProvider::class, 'label' => 'Tasks', 'color' => 'green']])
            ->actions(FakeHandler::class)
            ->settings(new CalendarSettings(features: ['create' => true, 'recurrence' => false]))
            ->build();
    }
}
