<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Karnoweb\Calendar\Calendar\IsoDate;
use Karnoweb\Calendar\Calendar\JalaliCalendarSystem;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\OccurrenceData;
use Karnoweb\Calendar\Data\RecurrenceSeriesData;
use Karnoweb\Calendar\Enums\RecurrenceFrequency;

/**
 * Recurrence on the Solar Hijri calendar. It steps through Jalali months/years itself and never
 * emulates them with Gregorian month arithmetic.
 *
 * Day-of-month policy (documented in docs/RECURRENCE.md): a monthly/yearly rule on day 30/31 (or
 * Esfand 30 in a non-leap year) is CLAMPED to the last valid day of that month, so every period
 * still yields exactly one occurrence. This differs from RFC 5545, which would skip such months.
 */
final class JalaliRecurrenceExpander
{
    public function __construct(private readonly JalaliCalendarSystem $jalali = new JalaliCalendarSystem) {}

    /** @return list<OccurrenceData> */
    public function occurrences(RecurrenceSeriesData $series, string $sourceKey, string $eventId, DateRange $range, RecurrenceLimitPolicy $policy): array
    {
        $tz = new DateTimeZone($series->timezone);
        $utc = new DateTimeZone('UTC');
        $rule = $series->rule;

        $startLocal = $series->allDay
            ? new DateTimeImmutable($series->start->format('Y-m-d').' 00:00:00', $tz)
            : $series->start->setTimezone($tz);
        $endLocal = $series->allDay
            ? new DateTimeImmutable($series->end->format('Y-m-d').' 00:00:00', $tz)
            : $series->end->setTimezone($tz);
        $startIso = $startLocal->format('Y-m-d');
        $time = $startLocal->format('H:i:s');
        $wall = (new DateTimeImmutable($endLocal->format('Y-m-d H:i:s'), $utc))->getTimestamp()
            - (new DateTimeImmutable($startLocal->format('Y-m-d H:i:s'), $utc))->getTimestamp();

        $out = [];
        $generated = 0;
        $iterations = 0;
        foreach ($this->dates($series, $startIso) as $iso) {
            if (++$iterations > $policy->maxIterations) {
                break;
            }
            $occStart = new DateTimeImmutable($iso.' '.$time, $tz);
            if ($rule->until !== null && $occStart > $rule->until) {
                break;
            }
            if ($rule->count !== null && $generated >= $rule->count) {
                break;
            }
            $generated++;
            if ($occStart >= $range->endExclusive) {
                break;
            }

            $naiveEnd = (new DateTimeImmutable($iso.' '.$time, $utc))->modify(($wall >= 0 ? '+' : '').$wall.' seconds');
            $occEnd = new DateTimeImmutable($naiveEnd->format('Y-m-d H:i:s'), $tz);
            if (! $range->overlaps($occStart, $occEnd)) {
                continue;
            }
            $key = RecurrenceExceptionApplier::keyFor($occStart, $series->allDay);
            $out[] = new OccurrenceData(new EventReference($sourceKey, $eventId, $key), $occStart, $occEnd, $occStart, $series->version, false, $series->allDay);
            if (count($out) >= $policy->maxOccurrences) {
                break;
            }
        }

        return $out;
    }

    /** @return Generator<int, string> Gregorian ISO dates of every occurrence, ascending, unbounded. */
    private function dates(RecurrenceSeriesData $series, string $startIso): Generator
    {
        $rule = $series->rule;
        [$sy, $sm, $sd] = $this->jalali->fromIso($startIso);

        switch ($rule->frequency) {
            case RecurrenceFrequency::Daily:
                for ($k = 0; ; $k++) {
                    yield IsoDate::addDays($startIso, $k * $rule->interval);
                }
                // no break
            case RecurrenceFrequency::Weekly:
                $first = $this->jalali->defaultFirstWeekday();
                $days = $rule->byWeekday !== [] ? $rule->byWeekday : [IsoDate::weekday($startIso)];
                usort($days, fn (int $a, int $b) => (($a - $first + 7) % 7) <=> (($b - $first + 7) % 7));
                $weekStart = IsoDate::startOfWeek($startIso, $first);
                for ($k = 0; ; $k++) {
                    $base = IsoDate::addDays($weekStart, 7 * $k * $rule->interval);
                    foreach ($days as $wd) {
                        $iso = IsoDate::addDays($base, ($wd - $first + 7) % 7);
                        if ($iso >= $startIso) {
                            yield $iso;
                        }
                    }
                }
                // no break
            case RecurrenceFrequency::Monthly:
                $dom = $rule->monthDay ?? $sd;
                for ($k = 0; ; $k++) {
                    $index = $sy * 12 + ($sm - 1) + $k * $rule->interval;
                    $y = intdiv($index, 12);
                    $m = $index % 12 + 1;
                    if ($y > 2990) {
                        return;
                    }
                    $iso = $this->jalali->toIso($y, $m, min($dom, $this->jalali->daysInMonth($y, $m)));
                    if ($iso >= $startIso) {
                        yield $iso;
                    }
                }
                // no break
            case RecurrenceFrequency::Yearly:
                $dom = $rule->monthDay ?? $sd;
                for ($k = 0; ; $k++) {
                    $y = $sy + $k * $rule->interval;
                    if ($y > 2990) {
                        return;
                    }
                    yield $this->jalali->toIso($y, $sm, min($dom, $this->jalali->daysInMonth($y, $sm)));
                }
        }
    }
}
