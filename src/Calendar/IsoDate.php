<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/** Pure helpers on Gregorian "Y-m-d" strings (timezone-free calendar dates). */
final class IsoDate
{
    public static function addDays(string $iso, int $days): string
    {
        return self::dt($iso)->modify(($days >= 0 ? '+' : '').$days.' days')->format('Y-m-d');
    }

    /** 0 = Sunday ... 6 = Saturday. */
    public static function weekday(string $iso): int
    {
        return (int) self::dt($iso)->format('w');
    }

    /** Whole days from $a to $b (negative when $b is before $a). */
    public static function diffDays(string $a, string $b): int
    {
        $diff = self::dt($a)->diff(self::dt($b));

        return (int) $diff->format('%r%a');
    }

    /** First day of the week containing $iso, given the first weekday (0=Sun..6=Sat). */
    public static function startOfWeek(string $iso, int $firstWeekday): string
    {
        return self::addDays($iso, -((self::weekday($iso) - $firstWeekday + 7) % 7));
    }

    public static function isValid(string $iso): bool
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $iso, new DateTimeZone('UTC'));

        return $dt !== false && $dt->format('Y-m-d') === $iso;
    }

    private static function dt(string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso, new DateTimeZone('UTC'));
    }
}
