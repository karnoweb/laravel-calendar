<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;

final readonly class EventPage
{
    /**
     * @param  list<CalendarEventData>  $events
     * @param  int|null  $total  null when the count is unknown
     * @param  bool  $truncated  provider dropped results because of its own safety cap
     */
    public function __construct(
        public array $events,
        public bool $hasMore,
        public ?string $nextCursor,
        public ?int $total,
        public string $requestKey,
        public bool $truncated = false,
    ) {
        if ($hasMore && ($nextCursor === null || $nextCursor === '')) {
            throw new InvalidArgumentException('hasMore requires nextCursor.');
        }
        foreach ($events as $event) {
            if (! $event instanceof CalendarEventData) {
                throw new InvalidArgumentException('EventPage may only contain CalendarEventData.');
            }
        }
    }

    public static function empty(string $requestKey = ''): self
    {
        return new self([], false, null, 0, $requestKey);
    }

    public function countExact(): bool
    {
        return $this->total !== null;
    }
}
