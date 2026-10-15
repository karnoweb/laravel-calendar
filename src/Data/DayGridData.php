<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

final readonly class DayGridData
{
    /** @param list<int> $timeSlots hour numbers 0..23 */
    public function __construct(
        public CalendarDayData $day,
        public array $timeSlots,
        public DateRange $requestRange,
    ) {}
}
