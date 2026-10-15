<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Data\CalendarNavigationState;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\DayGridData;
use Karnoweb\Calendar\Data\MonthGridData;
use Karnoweb\Calendar\Data\WeekGridData;
use Karnoweb\Calendar\Enums\CalendarView;

/** Shared date engine for all four views: visible range, grids, navigation. */
final class CalendarEngine
{
    public function __construct(
        private readonly CalendarSystemRegistry $systems,
        private readonly MonthGridBuilder $month,
        private readonly WeekGridBuilder $week,
        private readonly DayGridBuilder $day,
        private readonly ListRangeBuilder $list,
    ) {}

    public function system(CalendarNavigationState $state): CalendarSystemContract
    {
        return $this->systems->get($state->system);
    }

    public function firstWeekday(CalendarNavigationState $state, CalendarSettings $settings): int
    {
        return $settings->firstWeekday ?? $this->system($state)->defaultFirstWeekday();
    }

    public function cursor(CalendarNavigationState $state): CalendarCursor
    {
        return new CalendarCursor($this->system($state));
    }

    public function today(CalendarNavigationState $state): string
    {
        return $this->cursor($state)->today($state->timezone);
    }

    /** The REAL range that must be requested from providers for this view (padded grid for month). */
    public function range(CalendarNavigationState $state, CalendarSettings $settings): DateRange
    {
        $system = $this->system($state);

        return match ($state->view) {
            CalendarView::Month => $this->month->build($system, $state->date, $this->firstWeekday($state, $settings), $this->today($state), $state->timezone)->requestRange,
            CalendarView::Week => $this->week->build($system, $state->date, $this->firstWeekday($state, $settings), $this->today($state), $state->timezone)->requestRange,
            CalendarView::Day => $this->day->range($state->date, $state->timezone),
            CalendarView::List => $this->list->range($system, $state->date, $state->timezone),
        };
    }

    public function monthGrid(CalendarNavigationState $state, CalendarSettings $settings): MonthGridData
    {
        return $this->month->build($this->system($state), $state->date, $this->firstWeekday($state, $settings), $this->today($state), $state->timezone);
    }

    public function weekGrid(CalendarNavigationState $state, CalendarSettings $settings): WeekGridData
    {
        return $this->week->build($this->system($state), $state->date, $this->firstWeekday($state, $settings), $this->today($state), $state->timezone);
    }

    public function dayGrid(CalendarNavigationState $state): DayGridData
    {
        return $this->day->build($this->system($state), $state->date, $this->today($state), $state->timezone);
    }

    public function previous(CalendarNavigationState $state): CalendarNavigationState
    {
        return $state->with(date: $this->cursor($state)->previous($state->date, $state->view));
    }

    public function next(CalendarNavigationState $state): CalendarNavigationState
    {
        return $state->with(date: $this->cursor($state)->next($state->date, $state->view));
    }

    public function goToToday(CalendarNavigationState $state): CalendarNavigationState
    {
        return $state->with(date: $this->today($state));
    }

    /** Jump to a year/month typed in the DISPLAY system (e.g. 1405/7 when Jalali). */
    public function goTo(CalendarNavigationState $state, int $year, int $month): CalendarNavigationState
    {
        return $state->with(date: $this->cursor($state)->withYearMonth($state->date, $year, $month));
    }

    /** Switching system keeps the same real instant/day; only labels change. */
    public function switchSystem(CalendarNavigationState $state, \Karnoweb\Calendar\Enums\CalendarSystem $system): CalendarNavigationState
    {
        return $state->with(system: $system);
    }
}
