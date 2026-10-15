<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Data\CalendarEventData;

/** Splits events into days and places overlapping timed events into side-by-side lanes. */
final class EventLayoutEngine
{
    /**
     * Every day an event touches (within $isoDays) gets the event, so a multi-day event is listed once per day
     * but is the SAME event (same reference) — views mark continuation days.
     *
     * @param  list<CalendarEventData>  $events
     * @param  list<string>  $isoDays
     * @return array<string, list<CalendarEventData>>
     */
    public function byDay(array $events, array $isoDays, string $timezone): array
    {
        $map = array_fill_keys($isoDays, []);
        if ($isoDays === []) {
            return $map;
        }
        $min = $isoDays[0];
        $max = $isoDays[array_key_last($isoDays)];
        foreach ($events as $event) {
            $from = max($event->firstDate($timezone), $min);
            $to = min($event->lastDate($timezone), $max);
            for ($d = $from; $d <= $to; $d = IsoDate::addDays($d, 1)) {
                $map[$d][] = $event;
            }
        }

        return $map;
    }

    /**
     * Timed (non all-day) events of ONE day with minute offsets and lane assignment.
     *
     * @param  list<CalendarEventData>  $dayEvents events already filtered to this day
     * @return list<array{event: CalendarEventData, startMinute:int, endMinute:int, lane:int, lanes:int, continuesBefore:bool, continuesAfter:bool}>
     */
    public function timeLayout(array $dayEvents, string $iso, string $timezone): array
    {
        $items = [];
        foreach ($dayEvents as $event) {
            if ($event->allDay) {
                continue;
            }
            $start = $event->startAt($timezone);
            $end = $event->endAt($timezone);
            $sameStartDay = $start->format('Y-m-d') === $iso;
            $sameEndDay = $end->format('Y-m-d') === $iso;
            $startMin = $sameStartDay ? (int) $start->format('G') * 60 + (int) $start->format('i') : 0;
            $endMin = $sameEndDay ? (int) $end->format('G') * 60 + (int) $end->format('i') : 1440;
            if ($endMin <= $startMin) {
                $endMin = min(1440, $startMin + 15);
            }
            $items[] = [
                'event' => $event, 'startMinute' => $startMin, 'endMinute' => $endMin, 'lane' => 0, 'lanes' => 1,
                'continuesBefore' => ! $sameStartDay, 'continuesAfter' => ! $sameEndDay,
            ];
        }
        usort($items, fn ($a, $b) => [$a['startMinute'], -$a['endMinute']] <=> [$b['startMinute'], -$b['endMinute']]);

        // Cluster overlapping items, then assign the lowest free lane inside each cluster.
        $clusters = [];
        $current = [];
        $clusterEnd = -1;
        foreach ($items as $item) {
            if ($current !== [] && $item['startMinute'] >= $clusterEnd) {
                $clusters[] = $current;
                $current = [];
                $clusterEnd = -1;
            }
            $current[] = $item;
            $clusterEnd = max($clusterEnd, $item['endMinute']);
        }
        if ($current !== []) {
            $clusters[] = $current;
        }

        $out = [];
        foreach ($clusters as $cluster) {
            $laneEnds = [];
            foreach ($cluster as $i => $item) {
                $lane = null;
                foreach ($laneEnds as $l => $end) {
                    if ($end <= $item['startMinute']) {
                        $lane = $l;
                        break;
                    }
                }
                $lane ??= count($laneEnds);
                $laneEnds[$lane] = $item['endMinute'];
                $cluster[$i]['lane'] = $lane;
            }
            $lanes = count($laneEnds);
            foreach ($cluster as $item) {
                $item['lanes'] = $lanes;
                $out[] = $item;
            }
        }

        return $out;
    }
}
