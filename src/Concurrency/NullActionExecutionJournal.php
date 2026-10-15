<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Concurrency;

use Karnoweb\Calendar\Contracts\ActionExecutionJournal;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;

/** No store => no dedupe and NO exactly-once claim. */
final class NullActionExecutionJournal implements ActionExecutionJournal
{
    public function find(string $operationId, CalendarContext $context): ?ActionResult
    {
        return null;
    }

    public function record(string $operationId, CalendarContext $context, ActionResult $result): void {}

    public function guaranteesExactlyOnce(): bool
    {
        return false;
    }
}
