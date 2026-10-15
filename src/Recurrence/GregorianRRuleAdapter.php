<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\OccurrenceData;
use Karnoweb\Calendar\Data\RecurrenceSeriesData;
use Karnoweb\Calendar\Enums\RecurrenceFrequency;
use RRule\RRule;

/** Gregorian recurrence through RFC 5545 RRULE (rlanvin/php-rrule). Wall-clock time is preserved across DST. */
final class GregorianRRuleAdapter
{
    private const DAYS = [0 => 'SU', 1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA'];

    /**
     * @return list<OccurrenceData> occurrences overlapping $range (before exceptions), at most $limit
     */
    public function occurrences(RecurrenceSeriesData $series, string $sourceKey, string $eventId, DateRange $range, int $limit): array
    {
        $tz = new DateTimeZone($series->timezone);
        $rule = $series->rule;
        $startLocal = $series->allDay
            ? new DateTimeImmutable($series->start->format('Y-m-d').' 00:00:00', $tz)
            : $series->start->setTimezone($tz);
        $endLocal = $series->allDay
            ? new DateTimeImmutable($series->end->format('Y-m-d').' 00:00:00', $tz)
            : $series->end->setTimezone($tz);

        $utc = new DateTimeZone('UTC');
        $wall = (new DateTimeImmutable($endLocal->format('Y-m-d H:i:s'), $utc))->getTimestamp()
            - (new DateTimeImmutable($startLocal->format('Y-m-d H:i:s'), $utc))->getTimestamp();

        $parts = [
            'FREQ' => $rule->frequency->value,
            'INTERVAL' => $rule->interval,
            'DTSTART' => $startLocal,
        ];
        if ($rule->frequency === RecurrenceFrequency::Weekly && $rule->byWeekday !== []) {
            $parts['BYDAY'] = array_map(fn (int $d) => self::DAYS[$d], $rule->byWeekday);
            $parts['WKST'] = 'MO';
        }
        if ($rule->monthDay !== null) {
            $parts['BYMONTHDAY'] = $rule->monthDay;
        }
        if ($rule->count !== null) {
            $parts['COUNT'] = $rule->count;
        }
        if ($rule->until !== null) {
            $parts['UNTIL'] = $rule->until;
        }

        $rrule = new RRule($parts);
        $begin = $range->startInclusive->modify('-'.max(0, $wall).' seconds')->setTimezone($tz);
        $until = $range->endExclusive->modify('-1 second')->setTimezone($tz);

        $out = [];
        foreach ($rrule->getOccurrencesBetween($begin, $until, $limit) as $occ) {
            $occStart = DateTimeImmutable::createFromInterface($occ)->setTimezone($tz);
            $naiveEnd = (new DateTimeImmutable($occStart->format('Y-m-d H:i:s'), $utc))->modify(($wall >= 0 ? '+' : '').$wall.' seconds');
            $occEnd = new DateTimeImmutable($naiveEnd->format('Y-m-d H:i:s'), $tz);

            if ($series->allDay) {
                $occStart = new DateTimeImmutable($occStart->format('Y-m-d').' 00:00:00', $tz);
                $occEnd = new DateTimeImmutable($occEnd->format('Y-m-d').' 00:00:00', $tz);
            }
            if (! $range->overlaps($occStart, $occEnd)) {
                continue;
            }
            $key = RecurrenceExceptionApplier::keyFor($occStart, $series->allDay);
            $out[] = new OccurrenceData(
                new EventReference($sourceKey, $eventId, $key),
                $occStart, $occEnd, $occStart, $series->version, false, $series->allDay,
            );
        }

        return $out;
    }
}
