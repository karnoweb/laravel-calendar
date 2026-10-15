<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Exceptions;

use RuntimeException;

final class CalendarRateLimited extends RuntimeException
{
    public function __construct(string $message = 'Calendar rate limit exceeded.')
    {
        parent::__construct($message, 429);
    }
}
