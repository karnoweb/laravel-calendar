<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

final readonly class WeekGridData
{
    /**
     * @param  list<CalendarDayData>  $days
     * @param  list<int>  $slots  hour numbers 0..23 rendered as rows
     */
    public function __construct(
        public array $days,
        public array $slots,
        public DateRange $requestRange,
    ) {}
}
