<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

final readonly class CalendarDayData
{
    /**
     * @param  string  $iso  Gregorian Y-m-d (identity of the day)
     * @param  int  $year  year in the DISPLAY system
     * @param  int  $weekday  0 = Sunday ... 6 = Saturday
     * @param  list<CalendarEventData>  $events
     */
    public function __construct(
        public string $iso,
        public int $year,
        public int $month,
        public int $day,
        public int $weekday,
        public bool $outsideMonth,
        public bool $today,
        public bool $weekend,
        public array $events = [],
    ) {}

    /** @param list<CalendarEventData> $events */
    public function withEvents(array $events): self
    {
        return new self($this->iso, $this->year, $this->month, $this->day, $this->weekday, $this->outsideMonth, $this->today, $this->weekend, $events);
    }
}
