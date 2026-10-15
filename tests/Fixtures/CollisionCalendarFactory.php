<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Fixtures;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Support\CalendarBuilder;

/** Two sources that both expose an event with the SAME id "1" (and a prefix-looking id): identity must stay (source, id). */
abstract class CollisionProvider implements EventProvider
{
    abstract protected function sourceKey(): string;

    abstract protected function title(): string;

    public function fetch(EventQuery $query, CalendarContext $context): EventPage
    {
        $events = [];
        foreach (['1', 'task:1'] as $id) {
            $e = $this->event($id);
            if ($query->range->overlaps($e->start, $e->end)) {
                $events[] = $e;
            }
        }

        return new EventPage($events, false, null, count($events), $query->requestKey);
    }

    public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData
    {
        return $reference->sourceKey === $this->sourceKey() && in_array($reference->eventId, ['1', 'task:1'], true) ? $this->event($reference->eventId) : null;
    }

    private function event(string $id): CalendarEventData
    {
        $tz = new DateTimeZone('UTC');

        return new CalendarEventData(
            new EventReference($this->sourceKey(), $id),
            $this->title().' #'.$id,
            new DateTimeImmutable('2026-07-15 06:00:00', $tz),
            new DateTimeImmutable('2026-07-15 07:00:00', $tz),
            false, '1', ActionCapabilities::none(),
        );
    }
}

final class CollisionTaskProvider extends CollisionProvider
{
    protected function sourceKey(): string
    {
        return 'tasks';
    }

    protected function title(): string
    {
        return 'Task';
    }
}

final class CollisionShiftProvider extends CollisionProvider
{
    protected function sourceKey(): string
    {
        return 'shifts';
    }

    protected function title(): string
    {
        return 'Shift';
    }
}

final class CollisionCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)->key($context->calendarKey)->sources([
            'tasks' => ['provider' => CollisionTaskProvider::class, 'label' => 'Tasks', 'color' => 'blue', 'readOnly' => true],
            'shifts' => ['provider' => CollisionShiftProvider::class, 'label' => 'Shifts', 'color' => 'orange', 'readOnly' => true],
        ])->build();
    }
}
