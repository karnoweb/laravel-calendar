<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;

/** Optional host hook, invoked after the host handler returned a result. The core never sends notifications. */
interface CalendarOperationHooks
{
    public function afterCommit(ActionCommand $command, ActionResult $result, CalendarContext $context): void;
}
