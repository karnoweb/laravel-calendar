<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\OccurrenceData;
use Karnoweb\Calendar\Data\RecurrenceExceptionData;
use Karnoweb\Calendar\Recurrence\RecurrenceExpander;
use Workbench\App\Models\DemoRecurringSeries;
use Workbench\App\Models\DemoTask;

/**
 * Personal tasks: standalone rows plus expanded occurrences of recurring series.
 * Occurrence identity is (series id, occurrence key); task ids and series ids live in different namespaces
 * because the occurrence key is part of the reference, never a prefix of the id.
 */
final class DemoTaskProvider extends AbstractDemoProvider
{
    public function __construct(private readonly RecurrenceExpander $expander) {}

    protected function sourceKey(): string
    {
        return 'tasks';
    }

    protected function kind(): string
    {
        return 'task';
    }

    protected function capabilities(DemoTask $task): ActionCapabilities
    {
        if ($task->status === 'locked') {
            return ActionCapabilities::none();
        }
        $movable = (bool) ($task->movable ?? true);

        return new ActionCapabilities(true, true, true, $movable, $movable);
    }

    public function fetch(EventQuery $query, CalendarContext $context): EventPage
    {
        $events = [];
        $plain = parent::fetch(new EventQuery($query->range, 1000, null, $query->search, $query->filters, $query->requestKey), $context);
        foreach ($plain->events as $e) {
            $events[] = $e;
        }

        $series = DemoRecurringSeries::query()->where('scope_key', $context->scopeKey)->get();
        foreach ($series as $s) {
            if ($query->search !== '' && ! str_contains(mb_strtolower($s->title), mb_strtolower($query->search))) {
                continue;
            }
            if (isset($query->filters['status']) && $query->filters['status'] !== 'open') {
                continue;
            }
            foreach ($this->expander->expand($s->toSeriesData(), 'tasks', (string) $s->id, $query->range) as $occ) {
                $events[] = $this->occurrenceEvent($s, $occ);
            }
        }

        usort($events, fn (CalendarEventData $a, CalendarEventData $b) => [$a->start, $a->reference->eventId, (string) $a->reference->occurrenceKey] <=> [$b->start, $b->reference->eventId, (string) $b->reference->occurrenceKey]);

        $offset = $query->cursor === null ? 0 : max(0, (int) $query->cursor);
        $slice = array_slice($events, $offset, $query->limit);
        $more = $offset + $query->limit < count($events);

        return new EventPage($slice, $more, $more ? (string) ($offset + $query->limit) : null, count($events), $query->requestKey);
    }

    public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData
    {
        if ($reference->occurrenceKey === null) {
            return parent::find($reference, $context);
        }
        if (! ctype_digit($reference->eventId)) {
            return null;
        }
        $s = DemoRecurringSeries::query()->where('scope_key', $context->scopeKey)->find((int) $reference->eventId);
        if ($s === null) {
            return null;
        }

        $series = $s->toSeriesData();
        $tz = new DateTimeZone($series->timezone);
        foreach ($series->exceptions as $ex) {
            if ($ex->originalOccurrenceKey !== $reference->occurrenceKey) {
                continue;
            }
            if ($ex->isCancelled()) {
                return null;
            }
            $r = $ex->replacement;

            return $this->build($s, $reference, new DateTimeImmutable((string) $r['start']), new DateTimeImmutable((string) $r['end']));
        }

        // Not an exception: the key must be a genuine occurrence of the rule.
        $format = $series->allDay ? '!Y-m-d' : '!Y-m-d\TH:i:s';
        $original = DateTimeImmutable::createFromFormat($format, $reference->occurrenceKey, $tz);
        if ($original === false) {
            return null;
        }
        $range = new \Karnoweb\Calendar\Data\DateRange($original, $original->modify('+1 second'), $series->timezone);
        foreach ($this->expander->expand($series, 'tasks', (string) $s->id, $range) as $occ) {
            if ($occ->eventRef->occurrenceKey === $reference->occurrenceKey) {
                return $this->occurrenceEvent($s, $occ);
            }
        }

        return null;
    }

    private function occurrenceEvent(DemoRecurringSeries $s, OccurrenceData $occ): CalendarEventData
    {
        return $this->build($s, $occ->eventRef, $occ->start, $occ->end);
    }

    private function build(DemoRecurringSeries $s, EventReference $ref, DateTimeImmutable $start, DateTimeImmutable $end): CalendarEventData
    {
        $utc = new DateTimeZone('UTC');
        if ($s->all_day) {
            $start = new DateTimeImmutable($start->setTimezone(new DateTimeZone($s->timezone))->format('Y-m-d').' 00:00:00', $utc);
            $end = new DateTimeImmutable($end->setTimezone(new DateTimeZone($s->timezone))->format('Y-m-d').' 00:00:00', $utc);
        }

        $movable = (bool) ($s->movable ?? true);

        return new CalendarEventData(
            $ref, $s->title, $start, $end, (bool) $s->all_day, (string) $s->version, new ActionCapabilities(true, true, true, $movable, $movable),
            $s->description, $s->location, null, null, null, null, true,
        );
    }
}
