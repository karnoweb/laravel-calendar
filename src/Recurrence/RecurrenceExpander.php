<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Recurrence;

use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\OccurrenceData;
use Karnoweb\Calendar\Data\RecurrenceSeriesData;
use Karnoweb\Calendar\Enums\CalendarSystem;

/**
 * Entry point for providers: expand a series inside a bounded range, then apply exceptions.
 * The result is deterministic, timezone-safe and never larger than the policy allows.
 */
final class RecurrenceExpander
{
    public function __construct(
        private readonly GregorianRRuleAdapter $gregorian = new GregorianRRuleAdapter,
        private readonly JalaliRecurrenceExpander $jalali = new JalaliRecurrenceExpander,
        private readonly RecurrenceExceptionApplier $exceptions = new RecurrenceExceptionApplier,
    ) {}

    /**
     * @return list<OccurrenceData> sorted by start, with stable occurrence keys
     *
     * @throws \InvalidArgumentException when the range exceeds the policy
     */
    public function expand(RecurrenceSeriesData $series, string $sourceKey, string $eventId, DateRange $range, ?RecurrenceLimitPolicy $policy = null): array
    {
        $policy ??= new RecurrenceLimitPolicy;
        $policy->assertRange($range);
        $policy->assertRule($series->rule);

        $raw = match ($series->rule->calendarSystem) {
            CalendarSystem::Jalali => $this->jalali->occurrences($series, $sourceKey, $eventId, $range, $policy),
            CalendarSystem::Gregorian => $this->gregorian->occurrences($series, $sourceKey, $eventId, $range, $policy->maxOccurrences),
        };

        $applied = $this->exceptions->apply($raw, $series, $range, $sourceKey, $eventId);

        return array_slice($applied, 0, $policy->maxOccurrences);
    }
}
