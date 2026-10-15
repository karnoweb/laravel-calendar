<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Exceptions;

use RuntimeException;

/** Host handlers may throw this on stale expectedVersion; the dispatcher maps it to ActionStatus::Conflict. */
final class CalendarConflict extends RuntimeException
{
    public function __construct(string $message = 'Calendar version conflict.')
    {
        parent::__construct($message, 409);
    }
}
