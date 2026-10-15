<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use Illuminate\Support\Str;

/** Unique id per user intent. A retry of the SAME intent must reuse it; a new intent gets a new one. */
final class CalendarOperationId
{
    public static function generate(): string
    {
        return str_replace('-', '', (string) Str::uuid());
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/^[A-Za-z0-9_-]{8,64}$/', $value);
    }
}
