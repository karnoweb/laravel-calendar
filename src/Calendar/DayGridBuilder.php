<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Data\CalendarDayData;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\DayGridData;

final class DayGridBuilder
{
    public function build(CalendarSystemContract $system, string $iso, string $todayIso, string $timezone): DayGridData
    {
        [$y, $m, $d] = $system->fromIso($iso);
        $weekday = IsoDate::weekday($iso);
        $day = new CalendarDayData($iso, $y, $m, $d, $weekday, false, $iso === $todayIso, in_array($weekday, $system->weekendDays(), true));

        return new DayGridData($day, range(0, 23), $this->range($iso, $timezone));
    }

    public function range(string $iso, string $timezone): DateRange
    {
        return DateRange::ofLocalDays($iso, IsoDate::addDays($iso, 1), $timezone);
    }
}
