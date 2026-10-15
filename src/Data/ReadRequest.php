<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

/** What the UI asks the ReadDispatcher for. All values are sanitised by the dispatcher before use. */
final readonly class ReadRequest
{
    /**
     * @param  array<string, string>  $filters  sanitised "source.field" => value
     * @param  list<string>|null  $sources  enabled source keys; null = every source of the definition
     * @param  int  $pages  how many provider pages to follow per source (cursor is NEVER supplied by the browser)
     * @param  string|null  $cursor  server-issued composite cursor ({@see \Karnoweb\Calendar\Support\CursorPaginationMerger}); when set exactly one page per source is read
     */
    public function __construct(
        public DateRange $range,
        public string $search = '',
        public array $filters = [],
        public ?array $sources = null,
        public int $pages = 1,
        public ?string $cursor = null,
        public string $requestKey = '',
    ) {}
}
