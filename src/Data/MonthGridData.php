<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

final readonly class MonthGridData
{
    /**
     * @param  list<list<CalendarDayData>>  $rows  weeks, 7 days each (padded with outside-month days)
     * @param  list<int>  $weekdayOrder  weekday numbers in column order
     */
    public function __construct(
        public array $rows,
        public int $displayedYear,
        public int $displayedMonth,
        public array $weekdayOrder,
        public DateRange $requestRange,
    ) {}

    /** @return list<CalendarDayData> */
    public function days(): array
    {
        return array_merge(...$this->rows);
    }

    /** @param array<string, list<CalendarEventData>> $byDay */
    public function withEvents(array $byDay): self
    {
        $rows = array_map(
            fn (array $row) => array_map(fn (CalendarDayData $d) => $d->withEvents($byDay[$d->iso] ?? []), $row),
            $this->rows,
        );

        return new self($rows, $this->displayedYear, $this->displayedMonth, $this->weekdayOrder, $this->requestRange);
    }
}
