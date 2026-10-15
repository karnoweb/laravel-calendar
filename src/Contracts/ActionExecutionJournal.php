<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;

/**
 * Optional host idempotency store. The core only claims exactly-once semantics when
 * guaranteesExactlyOnce() returns true (a tested, transactional host journal).
 */
interface ActionExecutionJournal
{
    public function find(string $operationId, CalendarContext $context): ?ActionResult;

    public function record(string $operationId, CalendarContext $context, ActionResult $result): void;

    public function guaranteesExactlyOnce(): bool;
}
