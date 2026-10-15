<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\OccurrenceData;
use Karnoweb\Calendar\Data\RecurrenceSeriesData;
use Throwable;

/**
 * Applies EXDATE-like cancellations and per-occurrence overrides. Exceptions are matched on the
 * ORIGINAL occurrence key (original local start in the series timezone), which never changes when
 * an occurrence is moved, so a moved occurrence keeps its identity.
 */
final class RecurrenceExceptionApplier
{
    /** Stable identity of an occurrence: its original local start in the series timezone. */
    public static function keyFor(DateTimeImmutable $originalLocalStart, bool $allDay): string
    {
        return $allDay ? $originalLocalStart->format('Y-m-d') : $originalLocalStart->format('Y-m-d\TH:i:s');
    }

    /**
     * @param  list<OccurrenceData>  $occurrences  raw expansion
     * @return list<OccurrenceData>
     */
    public function apply(array $occurrences, RecurrenceSeriesData $series, DateRange $range, string $sourceKey, string $eventId): array
    {
        $byKey = [];
        foreach ($series->exceptions as $e) {
            $byKey[$e->originalOccurrenceKey] = $e;
        }
        $tz = new DateTimeZone($series->timezone);

        $out = [];
        $seen = [];
        foreach ($occurrences as $occ) {
            $seen[$occ->key()] = true;
            $exception = $byKey[$occ->key()] ?? null;
            if ($exception === null) {
                $out[] = $occ;

                continue;
            }
            if ($exception->isCancelled()) {
                continue;
            }
            $replaced = $this->replace($occ->key(), $occ->originalStart, (array) $exception->replacement, $series, $sourceKey, $eventId, $tz);
            if ($replaced !== null && $range->overlaps($replaced->start, $replaced->end)) {
                $out[] = $replaced;
            }
        }

        // An occurrence moved INTO the range whose original slot lies outside it.
        foreach ($byKey as $key => $exception) {
            if ($exception->isCancelled() || isset($seen[$key])) {
                continue;
            }
            $original = $this->parseKey((string) $key, $series, $tz);
            if ($original === null) {
                continue;
            }
            $replaced = $this->replace((string) $key, $original, (array) $exception->replacement, $series, $sourceKey, $eventId, $tz);
            if ($replaced !== null && $range->overlaps($replaced->start, $replaced->end)) {
                $out[] = $replaced;
            }
        }

        usort($out, fn (OccurrenceData $a, OccurrenceData $b) => [$a->start->getTimestamp(), $a->key()] <=> [$b->start->getTimestamp(), $b->key()]);

        return $out;
    }

    /** @param array<string, mixed> $replacement */
    private function replace(string $key, DateTimeImmutable $original, array $replacement, RecurrenceSeriesData $series, string $sourceKey, string $eventId, DateTimeZone $tz): ?OccurrenceData
    {
        try {
            $start = $series->allDay
                ? new DateTimeImmutable(substr((string) $replacement['start'], 0, 10).' 00:00:00', $tz)
                : new DateTimeImmutable((string) $replacement['start']);
            $end = $series->allDay
                ? new DateTimeImmutable(substr((string) $replacement['end'], 0, 10).' 00:00:00', $tz)
                : new DateTimeImmutable((string) $replacement['end']);
        } catch (Throwable) {
            return null;
        }
        if ($end <= $start) {
            return null;
        }

        return new OccurrenceData(new EventReference($sourceKey, $eventId, $key), $start, $end, $original, $series->version, true, $series->allDay);
    }

    private function parseKey(string $key, RecurrenceSeriesData $series, DateTimeZone $tz): ?DateTimeImmutable
    {
        $format = $series->allDay ? '!Y-m-d' : '!Y-m-d\TH:i:s';
        $dt = DateTimeImmutable::createFromFormat($format, $key, $tz);

        return $dt !== false ? $dt : null;
    }
}
