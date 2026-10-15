<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RecurrenceSeriesData
{
    /** @param list<RecurrenceExceptionData> $exceptions */
    public function __construct(
        public string $seriesId,
        public string $timezone,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public RecurrenceRuleData $rule,
        public array $exceptions = [],
        public ?string $version = null,
        public bool $allDay = false,
    ) {
        if ($seriesId === '') {
            throw new InvalidArgumentException('Series id is required.');
        }
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Invalid series timezone.');
        }
        if ($allDay ? $end->format('Y-m-d') <= $start->format('Y-m-d') : $end <= $start) {
            throw new InvalidArgumentException('Series end must follow start.');
        }
        foreach ($exceptions as $e) {
            if (! $e instanceof RecurrenceExceptionData) {
                throw new InvalidArgumentException('Exceptions must be RecurrenceExceptionData.');
            }
        }
    }

    public function with(?string $seriesId = null, ?DateTimeImmutable $start = null, ?DateTimeImmutable $end = null, ?RecurrenceRuleData $rule = null, ?array $exceptions = null, ?string $version = null): self
    {
        return new self(
            $seriesId ?? $this->seriesId,
            $this->timezone,
            $start ?? $this->start,
            $end ?? $this->end,
            $rule ?? $this->rule,
            $exceptions ?? $this->exceptions,
            $version ?? $this->version,
            $this->allDay,
        );
    }
}
