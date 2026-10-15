<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Data\DateRange;

/** The list view covers exactly one display-system month; paging happens inside it (cursor-based). */
final class ListRangeBuilder
{
    public function range(CalendarSystemContract $system, string $cursorIso, string $timezone): DateRange
    {
        [$y, $m] = $system->fromIso($cursorIso);
        $first = $system->toIso($y, $m, 1);
        $last = $system->toIso($y, $m, $system->daysInMonth($y, $m));

        return DateRange::ofLocalDays($first, IsoDate::addDays($last, 1), $timezone);
    }
}
