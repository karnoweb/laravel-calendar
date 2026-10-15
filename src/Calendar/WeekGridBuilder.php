<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Data\CalendarDayData;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\WeekGridData;

final class WeekGridBuilder
{
    public function build(CalendarSystemContract $system, string $cursorIso, int $firstWeekday, string $todayIso, string $timezone): WeekGridData
    {
        $start = IsoDate::startOfWeek($cursorIso, $firstWeekday);
        [, $cursorMonth] = $system->fromIso($cursorIso);
        $weekend = $system->weekendDays();

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $iso = IsoDate::addDays($start, $i);
            [$y, $m, $d] = $system->fromIso($iso);
            $weekday = IsoDate::weekday($iso);
            $days[] = new CalendarDayData($iso, $y, $m, $d, $weekday, $m !== $cursorMonth, $iso === $todayIso, in_array($weekday, $weekend, true));
        }

        return new WeekGridData(
            $days, range(0, 23),
            DateRange::ofLocalDays($start, IsoDate::addDays($start, 7), $timezone),
        );
    }
}
