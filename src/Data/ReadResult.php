<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

final readonly class ReadResult
{
    /**
     * @param  list<CalendarEventData>  $events  merged, stable-sorted
     * @param  array<string, array{count:int, hasMore:bool, error:?string}>  $sources
     */
    public function __construct(
        public array $events,
        public bool $hasMore,
        public bool $truncated,
        public array $sources,
        public string $requestKey,
        public ?string $nextCursor = null,
    ) {}

    /** @return list<string> keys of sources that failed or were denied */
    public function failedSources(): array
    {
        return array_keys(array_filter($this->sources, fn (array $s) => $s['error'] !== null));
    }
}
