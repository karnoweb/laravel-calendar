<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Fixtures;

use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;
use RuntimeException;

final class FakeProvider implements EventProvider
{
    public function fetch(EventQuery $query, CalendarContext $context): EventPage
    {
        FakeStore::$fetchCalls++;
        if (FakeStore::$failFetch) {
            throw new RuntimeException('provider down');
        }
        $events = [];
        foreach (FakeStore::$rows as $row) {
            if ($row['scope'] !== $context->scopeKey || ! $query->range->overlaps($row['start'], $row['end'])) {
                continue;
            }
            if ($query->search !== '' && ! str_contains(mb_strtolower($row['title']), mb_strtolower($query->search))) {
                continue;
            }
            $events[] = $this->toEvent($row, 'tasks');
        }
        usort($events, fn (CalendarEventData $a, CalendarEventData $b) => $a->start <=> $b->start ?: strcmp($a->reference->eventId, $b->reference->eventId));

        $offset = $query->cursor === null ? 0 : (int) $query->cursor;
        $slice = array_slice($events, $offset, $query->limit);
        $more = $offset + $query->limit < count($events);

        return new EventPage($slice, $more, $more ? (string) ($offset + $query->limit) : null, count($events), $query->requestKey);
    }

    public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData
    {
        $row = FakeStore::$rows[$reference->eventId] ?? null;
        if ($row === null || $row['scope'] !== $context->scopeKey) {
            return null;
        }

        return $this->toEvent($row, $reference->sourceKey);
    }

    /** @param array<string, mixed> $row */
    private function toEvent(array $row, string $source): CalendarEventData
    {
        return new CalendarEventData(
            new EventReference($source, (string) $row['id']),
            (string) $row['title'],
            $row['start'],
            $row['end'],
            (bool) $row['allDay'],
            (string) $row['version'],
            $row['canEdit'] ? ActionCapabilities::all() : ActionCapabilities::none(),
            $row['description'] ?? null,
            $row['location'] ?? null,
            $row['color'] ?? null,
            null,
            null,
            null,
            (bool) $row['recurring'],
        );
    }
}
