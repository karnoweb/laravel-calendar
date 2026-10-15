<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;

final class GregorianCalendarSystem implements CalendarSystemContract
{
    public function system(): CalendarSystem
    {
        return CalendarSystem::Gregorian;
    }

    public function fromIso(string $isoDate): array
    {
        if (! IsoDate::isValid($isoDate)) {
            throw new InvalidCalendarDate('Malformed date.');
        }
        [$y, $m, $d] = array_map('intval', explode('-', $isoDate));

        return [$y, $m, $d];
    }

    public function toIso(int $year, int $month, int $day): string
    {
        if (! $this->isValid($year, $month, $day)) {
            throw new InvalidCalendarDate("Invalid Gregorian date {$year}-{$month}-{$day}.");
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    public function daysInMonth(int $year, int $month): int
    {
        return match ($month) {
            1, 3, 5, 7, 8, 10, 12 => 31,
            4, 6, 9, 11 => 30,
            2 => $this->isLeapYear($year) ? 29 : 28,
            default => 0,
        };
    }

    public function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    public function isValid(int $year, int $month, int $day): bool
    {
        return $year >= 1 && $year <= 9999 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= $this->daysInMonth($year, $month);
    }

    public function defaultFirstWeekday(): int
    {
        return (int) config('calendar.first_weekday_gregorian', 0);
    }

    public function weekendDays(): array
    {
        return (array) config('calendar.weekend_days_gregorian', [0, 6]);
    }
}
