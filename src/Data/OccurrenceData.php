<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use DateTimeImmutable;

final readonly class OccurrenceData
{
    public function __construct(
        public EventReference $eventRef,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public DateTimeImmutable $originalStart,
        public ?string $seriesVersion = null,
        public bool $replaced = false,
        public bool $allDay = false,
    ) {}

    public function key(): string
    {
        return (string) $this->eventRef->occurrenceKey;
    }
}
