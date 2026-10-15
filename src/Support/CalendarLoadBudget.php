<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Exceptions\CalendarRateLimited;

/** Hard ceilings per request: pages followed, events collected, provider fan-out. */
final class CalendarLoadBudget
{
    private int $events = 0;

    private int $queries = 0;

    public function __construct(
        private readonly int $maxEvents,
        private readonly int $maxQueries,
    ) {}

    public static function for(CalendarDefinition $definition, int $pages): self
    {
        $perResponse = $definition->settings->limit('maxEventsPerResponse');
        $maxPages = $definition->settings->limit('maxPages');
        $pages = max(1, min($pages, $maxPages));

        return new self(
            maxEvents: $perResponse * $pages * max(1, count($definition->sources)),
            maxQueries: $pages * max(1, count($definition->sources)),
        );
    }

    public function chargeQuery(): void
    {
        if (++$this->queries > $this->maxQueries) {
            throw new CalendarRateLimited('Provider query budget exceeded.');
        }
    }

    public function chargeEvents(int $count): bool
    {
        $this->events += $count;

        return $this->events <= $this->maxEvents;
    }

    public function queries(): int
    {
        return $this->queries;
    }
}
