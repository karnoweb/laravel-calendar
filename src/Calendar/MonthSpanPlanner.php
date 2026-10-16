<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Data\CalendarEventData;

/** Places all-day and multi-day events into week-row span lanes (month grid). */
final class MonthSpanPlanner
{
    /**
     * @param  list<CalendarEventData>  $events
     * @param  list<string>  $weekIsos  seven consecutive ISO days
     * @return list<array{event: CalendarEventData, colStart: int, colSpan: int, lane: int, labelIso: string, continuesBefore: bool, continuesAfter: bool}>
     */
    public function plan(array $events, array $weekIsos, string $timezone): array
    {
        if (count($weekIsos) !== 7) {
            return [];
        }
        $weekStart = $weekIsos[0];
        $weekEnd = $weekIsos[6];
        $candidates = [];
        foreach ($events as $event) {
            $first = $event->firstDate($timezone);
            $last = $event->lastDate($timezone);
            if ($first > $weekEnd || $last < $weekStart) {
                continue;
            }
            if (! $event->allDay && $first === $last) {
                continue;
            }
            $visStart = max($first, $weekStart);
            $visEnd = min($last, $weekEnd);
            $colStart = (int) array_search($visStart, $weekIsos, true);
            if ($colStart < 0) {
                continue;
            }
            $colSpan = IsoDate::diffDays($visStart, $visEnd) + 1;
            $candidates[] = [
                'event' => $event,
                'colStart' => $colStart,
                'colSpan' => $colSpan,
                'labelIso' => $visStart,
                'continuesBefore' => $first < $weekStart,
                'continuesAfter' => $last > $weekEnd,
                'sortKey' => [$event->allDay ? 0 : 1, -$colSpan, $visStart, $event->title],
            ];
        }
        usort($candidates, fn ($a, $b) => $a['sortKey'] <=> $b['sortKey']);

        $laneEnds = [];
        $out = [];
        foreach ($candidates as $item) {
            $lane = $this->lowestFreeLane($laneEnds, $item['colStart'], $item['colSpan']);
            $out[] = [
                'event' => $item['event'],
                'colStart' => $item['colStart'],
                'colSpan' => $item['colSpan'],
                'lane' => $lane,
                'labelIso' => $item['labelIso'],
                'continuesBefore' => $item['continuesBefore'],
                'continuesAfter' => $item['continuesAfter'],
            ];
        }

        return $out;
    }

    /** @param  array<int, int>  $laneEnds  lane => exclusive end column index */
    private function lowestFreeLane(array &$laneEnds, int $colStart, int $colSpan): int
    {
        $colEnd = $colStart + $colSpan;
        for ($lane = 0; ; $lane++) {
            if (($laneEnds[$lane] ?? 0) <= $colStart) {
                $laneEnds[$lane] = $colEnd;

                return $lane;
            }
        }
    }
}
