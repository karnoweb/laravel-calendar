<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Exceptions;

use RuntimeException;

final class GoogleCalendarException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message, $status);
    }

    public function isConflict(): bool
    {
        return $this->status === 412 || $this->status === 409;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404 || $this->status === 410;
    }

    public function isAuth(): bool
    {
        return $this->status === 401 || $this->status === 403;
    }
}
