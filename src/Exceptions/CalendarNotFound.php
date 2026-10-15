<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Exceptions;

use RuntimeException;

final class CalendarNotFound extends RuntimeException
{
    public function __construct(string $message = 'Calendar resource not found.')
    {
        parent::__construct($message, 404);
    }
}
