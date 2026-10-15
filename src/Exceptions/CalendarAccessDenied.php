<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Exceptions;

use RuntimeException;

/** Thrown on any scope/role/anonymous/source rejection. The message never contains private data. */
final class CalendarAccessDenied extends RuntimeException
{
    public function __construct(string $message = 'Calendar access denied.')
    {
        parent::__construct($message, 403);
    }
}
