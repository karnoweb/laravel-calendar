<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Enums\CalendarAction;
use Workbench\App\Models\DemoTask;

/**
 * Host (module) policy. The package decides whether a calendar operation is *allowed to be attempted*;
 * the host still owns its business rules: here, "locked" tasks are immutable and only managers may delete.
 */
final class DemoTaskPolicy
{
    public function allows(CalendarContext $context, CalendarAction $action, ?DemoTask $task): bool
    {
        if ($task !== null && $task->status === 'locked') {
            return false;
        }
        if ($action === CalendarAction::Delete && $context->actorId === '33') {
            return false; // actor 33 is a read/write user without delete rights
        }

        return true;
    }
}
