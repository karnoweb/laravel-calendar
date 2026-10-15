<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use Karnoweb\Calendar\Contracts\CalendarOperationHooks;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;

final class NullCalendarOperationHooks implements CalendarOperationHooks
{
    public function afterCommit(ActionCommand $command, ActionResult $result, CalendarContext $context): void {}
}
