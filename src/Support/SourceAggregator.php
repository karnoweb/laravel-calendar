<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\DateRange;

/** Merges per-source events: drops foreign/out-of-range items, dedupes on the composite key, stable sort. */
final class SourceAggregator
{
    /**
     * @param  array<string, list<CalendarEventData>>  $bySource  source key => events (in source order)
     * @return list<CalendarEventData>
     */
    public static function merge(array $bySource, DateRange $range, string $timezone): array
    {
        $order = array_flip(array_keys($bySource));
        $seen = [];
        $rows = [];
        foreach ($bySource as $sourceKey => $events) {
            foreach ($events as $event) {
                // Defence in depth: never trust a provider to stay inside its own source/range.
                if ($event->reference->sourceKey !== $sourceKey || ! $event->overlaps($range)) {
                    continue;
                }
                $id = EventReferenceCodec::encode($event->reference);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $rows[] = [
                    'event' => $event,
                    'day' => $event->firstDate($timezone),
                    'allDayRank' => $event->allDay ? 0 : 1,
                    'ts' => $event->startAt($timezone)->getTimestamp(),
                    'src' => $order[$sourceKey],
                    'id' => $event->reference->eventId,
                    'occ' => (string) $event->reference->occurrenceKey,
                ];
            }
        }
        usort($rows, static fn (array $a, array $b) => [$a['day'], $a['allDayRank'], $a['ts'], $a['src'], $a['id'], $a['occ']]
            <=> [$b['day'], $b['allDayRank'], $b['ts'], $b['src'], $b['id'], $b['occ']]);

        return array_map(static fn (array $r) => $r['event'], $rows);
    }
}
