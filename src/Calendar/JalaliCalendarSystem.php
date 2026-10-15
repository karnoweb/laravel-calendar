<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;
use Morilog\Jalali\CalendarUtils;

/** Solar Hijri (Jalali) calendar. Leap years follow morilog/jalali's arithmetic (33-year cycle algorithm). */
final class JalaliCalendarSystem implements CalendarSystemContract
{
    public function system(): CalendarSystem
    {
        return CalendarSystem::Jalali;
    }

    public function fromIso(string $isoDate): array
    {
        if (! IsoDate::isValid($isoDate)) {
            throw new InvalidCalendarDate('Malformed date.');
        }
        [$gy, $gm, $gd] = array_map('intval', explode('-', $isoDate));
        [$jy, $jm, $jd] = CalendarUtils::toJalali($gy, $gm, $gd);

        return [(int) $jy, (int) $jm, (int) $jd];
    }

    public function toIso(int $year, int $month, int $day): string
    {
        if (! $this->isValid($year, $month, $day)) {
            throw new InvalidCalendarDate("Invalid Jalali date {$year}/{$month}/{$day}.");
        }
        [$gy, $gm, $gd] = CalendarUtils::toGregorian($year, $month, $day);

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    public function daysInMonth(int $year, int $month): int
    {
        return match (true) {
            $month >= 1 && $month <= 6 => 31,
            $month >= 7 && $month <= 11 => 30,
            $month === 12 => $this->isLeapYear($year) ? 30 : 29,
            default => 0,
        };
    }

    public function isLeapYear(int $year): bool
    {
        return (bool) CalendarUtils::isLeapJalaliYear($year);
    }

    public function isValid(int $year, int $month, int $day): bool
    {
        return $year >= 1 && $year <= 3000 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= $this->daysInMonth($year, $month);
    }

    public function defaultFirstWeekday(): int
    {
        return (int) config('calendar.first_weekday_jalali', 6);
    }

    public function weekendDays(): array
    {
        return (array) config('calendar.weekend_days_jalali', [5]);
    }
}
