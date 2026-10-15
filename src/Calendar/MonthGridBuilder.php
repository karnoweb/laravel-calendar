<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Data\CalendarDayData;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\MonthGridData;

/** Builds the padded month grid (complete weeks) and the REAL request range it covers. */
final class MonthGridBuilder
{
    public function build(CalendarSystemContract $system, string $cursorIso, int $firstWeekday, string $todayIso, string $timezone): MonthGridData
    {
        [$year, $month] = $system->fromIso($cursorIso);
        $first = $system->toIso($year, $month, 1);
        $last = $system->toIso($year, $month, $system->daysInMonth($year, $month));

        $gridStart = IsoDate::startOfWeek($first, $firstWeekday);
        $lastColumn = (IsoDate::weekday($last) - $firstWeekday + 7) % 7;
        $gridEnd = IsoDate::addDays($last, 6 - $lastColumn); // inclusive

        $weekend = $system->weekendDays();
        $rows = [];
        $row = [];
        for ($iso = $gridStart; $iso <= $gridEnd; $iso = IsoDate::addDays($iso, 1)) {
            [$y, $m, $d] = $system->fromIso($iso);
            $weekday = IsoDate::weekday($iso);
            $row[] = new CalendarDayData($iso, $y, $m, $d, $weekday, ($y !== $year || $m !== $month), $iso === $todayIso, in_array($weekday, $weekend, true));
            if (count($row) === 7) {
                $rows[] = $row;
                $row = [];
            }
        }

        $order = [];
        for ($i = 0; $i < 7; $i++) {
            $order[] = ($firstWeekday + $i) % 7;
        }

        return new MonthGridData(
            $rows, $year, $month, $order,
            DateRange::ofLocalDays($gridStart, IsoDate::addDays($gridEnd, 1), $timezone),
        );
    }
}
