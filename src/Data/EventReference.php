<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;

/** Composite identity: (source, record, occurrence). Never a prefixed string. */
final readonly class EventReference
{
    public function __construct(
        public string $sourceKey,
        public string $eventId,
        public ?string $occurrenceKey = null,
    ) {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $sourceKey)) {
            throw new InvalidArgumentException('Invalid event source key.');
        }
        if ($eventId === '' || strlen($eventId) > 191) {
            throw new InvalidArgumentException('Invalid event id.');
        }
        if ($occurrenceKey !== null && ($occurrenceKey === '' || strlen($occurrenceKey) > 64)) {
            throw new InvalidArgumentException('Invalid occurrence key.');
        }
    }

    public function withoutOccurrence(): self
    {
        return new self($this->sourceKey, $this->eventId);
    }

    public function equals(self $other): bool
    {
        return $this->sourceKey === $other->sourceKey
            && $this->eventId === $other->eventId
            && $this->occurrenceKey === $other->occurrenceKey;
    }
}
