<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use Karnoweb\Calendar\Data\ActionCapabilities;
use Workbench\App\Models\DemoTask;

/** Reservations may be moved/resized but not created, edited or deleted from the calendar. */
final class DemoReservationProvider extends AbstractDemoProvider
{
    protected function sourceKey(): string
    {
        return 'reservations';
    }

    protected function kind(): string
    {
        return 'reservation';
    }

    protected function capabilities(DemoTask $task): ActionCapabilities
    {
        return new ActionCapabilities(canMove: $task->status !== 'locked', canResize: $task->status !== 'locked');
    }
}
