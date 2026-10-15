<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Concurrency;

use Karnoweb\Calendar\Exceptions\CalendarConflict;

/**
 * Optimistic-concurrency helper for host handlers. The package never overwrites silently:
 * a stale version must yield ActionStatus::Conflict and a refetch.
 */
final class ExpectedVersionGuard
{
    public static function matches(?string $expected, ?string $current): bool
    {
        if ($expected === null) {
            return true; // caller did not request a check
        }

        return $current !== null && hash_equals($current, $expected);
    }

    /** @throws CalendarConflict */
    public static function assert(?string $expected, ?string $current): void
    {
        if (! self::matches($expected, $current)) {
            throw new CalendarConflict;
        }
    }
}
