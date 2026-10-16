<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Events;

use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Enums\CalendarAction;

/**
 * Dispatched after a calendar write operation succeeds and its database transaction has committed.
 */
final readonly class CalendarEventMutated
{
    public CalendarAction $action;

    public function __construct(
        public string $calendarKey,
        public string $sourceKey,
        public ActionCommand $command,
        public ActionResult $result,
        public CalendarContext $context,
    ) {
        $this->action = $command->action;
    }
}
