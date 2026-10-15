<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;
use Workbench\App\Models\DemoTask;

/** Read side of one demo "kind". Every query is hard-scoped by CalendarContext::$scopeKey. */
abstract class AbstractDemoProvider implements EventProvider
{
    abstract protected function sourceKey(): string;

    abstract protected function kind(): string;

    protected function capabilities(DemoTask $task): ActionCapabilities
    {
        return ActionCapabilities::none();
    }

    public function fetch(EventQuery $query, CalendarContext $context): EventPage
    {
        $q = DemoTask::query()
            ->where('scope_key', $context->scopeKey)
            ->where('kind', $this->kind())
            ->where('starts_at', '<', $query->range->endExclusive->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $query->range->startInclusive->format('Y-m-d H:i:s'));

        if ($query->search !== '') {
            $q->where('title', 'like', '%'.addcslashes($query->search, '%_\\').'%');
        }
        if (isset($query->filters['status']) && is_string($query->filters['status'])) {
            $q->where('status', $query->filters['status']);
        }

        $offset = $query->cursor === null ? 0 : max(0, (int) $query->cursor);
        $rows = $q->orderBy('starts_at')->orderBy('id')->offset($offset)->limit($query->limit + 1)->get();
        $more = $rows->count() > $query->limit;
        $rows = $rows->take($query->limit);

        return new EventPage(
            $rows->map(fn (DemoTask $t) => $this->toEvent($t, $this->sourceKey()))->all(),
            $more,
            $more ? (string) ($offset + $query->limit) : null,
            null,
            $query->requestKey,
        );
    }

    public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData
    {
        if ($reference->occurrenceKey !== null || ! ctype_digit($reference->eventId)) {
            return null;
        }
        $task = DemoTask::query()
            ->where('scope_key', $context->scopeKey)
            ->where('kind', $this->kind())
            ->find((int) $reference->eventId);

        return $task === null ? null : $this->toEvent($task, $reference->sourceKey);
    }

    protected function toEvent(DemoTask $t, string $sourceKey): CalendarEventData
    {
        $utc = new DateTimeZone('UTC');
        // All-day events are floating dates (stored as date at 00:00 UTC); timed events are real instants.
        $start = $t->all_day ? new DateTimeImmutable($t->starts_at->format('Y-m-d').' 00:00:00', $utc) : DateTimeImmutable::createFromInterface($t->starts_at)->setTimezone($utc);
        $end = $t->all_day ? new DateTimeImmutable($t->ends_at->format('Y-m-d').' 00:00:00', $utc) : DateTimeImmutable::createFromInterface($t->ends_at)->setTimezone($utc);

        return new CalendarEventData(
            new EventReference($sourceKey, (string) $t->id),
            $t->title,
            $start,
            $end,
            $t->all_day,
            (string) $t->version,
            $this->capabilities($t),
            $t->description,
            $t->location,
            $t->color,
            null,
            $t->status,
        );
    }
}
