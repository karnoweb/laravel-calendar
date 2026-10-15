<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\RecurrenceExceptionData;
use Karnoweb\Calendar\Data\RecurrenceRuleData;
use Karnoweb\Calendar\Data\RecurrenceSeriesData;

/**
 * Computes the "this and following" split contract for host handlers: a TRUNCATED earlier series
 * (everything before the split occurrence is untouched) and a NEW later series starting at the split.
 * The package never persists anything; the host stores both series in one transaction.
 */
final class RecurrenceSeriesSplitter
{
    public function __construct(private readonly RecurrenceExpander $expander = new RecurrenceExpander) {}

    /**
     * @param  string  $newSeriesId  id the host assigned (or will assign) to the new series
     * @param  DateTimeImmutable|null  $newStart  replacement start of the new series (defaults to the original start of the split occurrence)
     * @param  DateTimeImmutable|null  $newEnd  replacement end of the new series
     * @return array{before: RecurrenceSeriesData, after: RecurrenceSeriesData}
     */
    public function split(RecurrenceSeriesData $series, string $splitOccurrenceKey, string $newSeriesId, ?DateTimeImmutable $newStart = null, ?DateTimeImmutable $newEnd = null): array
    {
        $tz = new DateTimeZone($series->timezone);
        $format = $series->allDay ? '!Y-m-d' : '!Y-m-d\TH:i:s';
        $splitLocal = DateTimeImmutable::createFromFormat($format, $splitOccurrenceKey, $tz);
        if ($splitLocal === false) {
            throw new InvalidArgumentException('Invalid split occurrence key.');
        }
        if ($splitLocal < ($series->allDay ? new DateTimeImmutable($series->start->format('Y-m-d').' 00:00:00', $tz) : $series->start)) {
            throw new InvalidArgumentException('Cannot split before the series start.');
        }

        // Occurrences strictly before the split (used to rebase COUNT).
        $beforeCount = $this->countBefore($series, $splitLocal);
        if ($beforeCount === 0) {
            throw new InvalidArgumentException('Splitting at the first occurrence is a whole-series change (use series.update).');
        }

        $rule = $series->rule;
        $beforeRule = $rule->count !== null ? $rule->withCount($beforeCount) : $rule->withUntil($splitLocal->modify('-1 second'));
        $afterRule = $rule->count !== null ? $rule->withCount(max(1, $rule->count - $beforeCount)) : $rule;

        $duration = $series->end->getTimestamp() - $series->start->getTimestamp();
        $after = $series->with(
            seriesId: $newSeriesId,
            start: $newStart ?? $splitLocal,
            end: $newEnd ?? ($newStart ?? $splitLocal)->modify('+'.$duration.' seconds'),
            rule: $afterRule,
            exceptions: $this->exceptionsFrom($series, $splitOccurrenceKey, true),
            version: '1',
        );
        $before = $series->with(rule: $beforeRule, exceptions: $this->exceptionsFrom($series, $splitOccurrenceKey, false));

        return ['before' => $before, 'after' => $after];
    }

    private function countBefore(RecurrenceSeriesData $series, DateTimeImmutable $splitLocal): int
    {
        $tz = new DateTimeZone($series->timezone);
        $start = $series->allDay ? new DateTimeImmutable($series->start->format('Y-m-d').' 00:00:00', $tz) : $series->start->setTimezone($tz);
        $rangeEnd = $splitLocal; // exclusive
        if ($rangeEnd <= $start) {
            return 0;
        }
        $plain = $series->with(exceptions: []);
        $range = new DateRange($start, $rangeEnd, $series->timezone);
        $count = 0;
        foreach ($this->expander->expand($plain, 'split', 'split', $range, new RecurrenceLimitPolicy(RecurrenceRuleData::MAX_COUNT, 36500, 200000)) as $occ) {
            if ($occ->originalStart < $splitLocal) {
                $count++;
            }
        }

        return $count;
    }

    /** @return list<RecurrenceExceptionData> */
    private function exceptionsFrom(RecurrenceSeriesData $series, string $splitKey, bool $atOrAfter): array
    {
        return array_values(array_filter(
            $series->exceptions,
            fn (RecurrenceExceptionData $e) => $atOrAfter ? $e->originalOccurrenceKey >= $splitKey : $e->originalOccurrenceKey < $splitKey,
        ));
    }
}
