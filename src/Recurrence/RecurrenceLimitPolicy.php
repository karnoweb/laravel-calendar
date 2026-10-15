<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Recurrence;

use InvalidArgumentException;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\RecurrenceRuleData;

/** Hard bounds that make recurrence expansion impossible to blow up (CPU, memory or response size). */
final class RecurrenceLimitPolicy
{
    public function __construct(
        public readonly int $maxOccurrences = 500,
        public readonly int $maxRangeDays = 366,
        public readonly int $maxIterations = 20000,
    ) {
        if ($maxOccurrences < 1 || $maxRangeDays < 1 || $maxIterations < 1) {
            throw new InvalidArgumentException('Recurrence limits must be positive.');
        }
    }

    public static function fromSettings(CalendarSettings $settings): self
    {
        return new self($settings->limit('maxOccurrences'), $settings->limit('maxRangeDays'));
    }

    public function assertRange(DateRange $range): void
    {
        if ($range->days() > $this->maxRangeDays) {
            throw new InvalidArgumentException('Recurrence expansion range is too large.');
        }
    }

    public function assertRule(RecurrenceRuleData $rule): void
    {
        if ($rule->count !== null && $rule->count > RecurrenceRuleData::MAX_COUNT) {
            throw new InvalidArgumentException('Recurrence count too large.');
        }
    }

    public function limit(?int $requested = null): int
    {
        return $requested === null ? $this->maxOccurrences : max(1, min($requested, $this->maxOccurrences));
    }
}
