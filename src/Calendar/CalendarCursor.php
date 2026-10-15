<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Enums\CalendarView;

/**
 * Cursor arithmetic in the DISPLAY calendar system. Month steps happen in the system's own months
 * (never via Gregorian addMonth) and the day is clamped to the last valid day of the target month.
 */
final class CalendarCursor
{
    public function __construct(private readonly CalendarSystemContract $system) {}

    public function today(string $timezone): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d');
    }

    public function previous(string $iso, CalendarView $view): string
    {
        return $this->step($iso, $view, -1);
    }

    public function next(string $iso, CalendarView $view): string
    {
        return $this->step($iso, $view, 1);
    }

    public function step(string $iso, CalendarView $view, int $direction): string
    {
        return match ($view) {
            CalendarView::Week => IsoDate::addDays($iso, 7 * $direction),
            CalendarView::Day => IsoDate::addDays($iso, $direction),
            CalendarView::Month, CalendarView::List => $this->addMonths($iso, $direction),
        };
    }

    public function addMonths(string $iso, int $months): string
    {
        [$y, $m, $d] = $this->system->fromIso($iso);
        $index = $y * 12 + ($m - 1) + $months;
        $ny = intdiv($index, 12);
        $nm = $index % 12 + 1;

        return $this->system->toIso($ny, $nm, min($d, $this->system->daysInMonth($ny, $nm)));
    }

    /** Jump to (year, month) in the display system, keeping the day clamped. */
    public function withYearMonth(string $iso, int $year, int $month): string
    {
        $month = max(1, min(12, $month));
        $year = max(1, $year);
        [, , $d] = $this->system->fromIso($iso);

        return $this->system->toIso($year, $month, min($d, $this->system->daysInMonth($year, $month)));
    }

    public function firstOfMonth(string $iso): string
    {
        [$y, $m] = $this->system->fromIso($iso);

        return $this->system->toIso($y, $m, 1);
    }

    public function lastOfMonth(string $iso): string
    {
        [$y, $m] = $this->system->fromIso($iso);

        return $this->system->toIso($y, $m, $this->system->daysInMonth($y, $m));
    }
}
