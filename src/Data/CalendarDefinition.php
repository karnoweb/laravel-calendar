<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use Karnoweb\Calendar\Contracts\ActionHandler;
use Karnoweb\Calendar\Contracts\EventProvider;

final readonly class CalendarDefinition
{
    public CalendarViews $views;

    /**
     * @param  array<string, class-string<EventProvider>>  $sources
     * @param  class-string<ActionHandler>|null  $handlerClass
     * @param  array<string, EventSourceData>  $sourceData
     * @param  list<CalendarFilterData>  $filters
     */
    public function __construct(
        public string $key,
        public array $sources,
        public ?string $handlerClass,
        public CalendarSettings $settings,
        ?CalendarViews $views = null,
        public array $sourceData = [],
        public array $filters = [],
    ) {
        $this->views = $views ?? new CalendarViews;
    }

    public function hasSource(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    public function source(string $key): EventSourceData
    {
        return $this->sourceData[$key] ?? new EventSourceData($key, $key);
    }

    /** @return list<string> */
    public function sourceKeys(): array
    {
        return array_keys($this->sources);
    }

    /** @return list<CalendarFilterData> */
    public function filtersFor(string $sourceKey): array
    {
        return array_values(array_filter($this->filters, fn (CalendarFilterData $f) => $f->sourceKey === $sourceKey));
    }
}
