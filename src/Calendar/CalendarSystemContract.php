<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;

/** A calendar system maps between its own (y, m, d) and the Gregorian ISO date used internally. */
interface CalendarSystemContract
{
    public function system(): CalendarSystem;

    /** @return array{0:int,1:int,2:int} [year, month, day] in this system */
    public function fromIso(string $isoDate): array;

    /** @throws InvalidCalendarDate when (y, m, d) does not exist in this system */
    public function toIso(int $year, int $month, int $day): string;

    public function daysInMonth(int $year, int $month): int;

    public function isLeapYear(int $year): bool;

    public function isValid(int $year, int $month, int $day): bool;

    /** 0 = Sunday ... 6 = Saturday. */
    public function defaultFirstWeekday(): int;

    /** @return list<int> 0 = Sunday ... 6 = Saturday */
    public function weekendDays(): array;
}
