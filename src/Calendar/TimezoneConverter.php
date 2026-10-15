<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;

/**
 * Local wall-clock <-> instant conversion with an explicit DST policy:
 *  - a non-existent local time (spring-forward gap) is REJECTED,
 *  - an ambiguous local time (fall-back fold) is REJECTED unless an explicit UTC offset is given.
 * Nothing is normalised silently.
 */
final class TimezoneConverter
{
    /**
     * @param  string  $local  "Y-m-d H:i" or "Y-m-d H:i:s"
     * @param  string|null  $offset  explicit offset such as "+03:30" or "Z" used to disambiguate a fold
     *
     * @throws InvalidCalendarDate
     */
    public function localToUtc(string $local, string $timezone, ?string $offset = null): DateTimeImmutable
    {
        $tz = new DateTimeZone($timezone);
        $naive = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', strlen($local) === 16 ? $local.':00' : $local, new DateTimeZone('UTC'));
        if ($naive === false || $naive->format('Y-m-d H:i:s') !== (strlen($local) === 16 ? $local.':00' : $local)) {
            throw new InvalidCalendarDate('Malformed local date/time.');
        }

        $base = $naive->getTimestamp();
        $offsets = [];
        foreach ([-86400, 0, 86400] as $delta) {
            $offsets[$tz->getOffset(new DateTimeImmutable('@'.($base + $delta)))] = true;
        }

        $candidates = [];
        foreach (array_keys($offsets) as $o) {
            $ts = $base - $o;
            if ($tz->getOffset(new DateTimeImmutable('@'.$ts)) === $o) {
                $candidates[$ts] = $o;
            }
        }
        ksort($candidates);

        if ($candidates === []) {
            throw new InvalidCalendarDate("The local time {$local} does not exist in {$timezone} (daylight-saving gap).", InvalidCalendarDate::GAP);
        }
        if (count($candidates) === 1) {
            return (new DateTimeImmutable('@'.array_key_first($candidates)));
        }

        if ($offset === null) {
            throw new InvalidCalendarDate("The local time {$local} is ambiguous in {$timezone} (daylight-saving fold); an explicit offset is required.", InvalidCalendarDate::AMBIGUOUS);
        }
        $wanted = $this->offsetSeconds($offset);
        foreach ($candidates as $ts => $o) {
            if ($o === $wanted) {
                return new DateTimeImmutable('@'.$ts);
            }
        }
        throw new InvalidCalendarDate('The given offset does not match any valid interpretation of this local time.', InvalidCalendarDate::AMBIGUOUS);
    }

    /**
     * UTC offsets ("+03:30") that are valid for an ambiguous local time (fold); empty when the time is not ambiguous.
     *
     * @return list<string>
     */
    public function candidateOffsets(string $local, string $timezone): array
    {
        $tz = new DateTimeZone($timezone);
        $naive = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', strlen($local) === 16 ? $local.':00' : $local, new DateTimeZone('UTC'));
        if ($naive === false) {
            return [];
        }
        $base = $naive->getTimestamp();
        $found = [];
        foreach ([-86400, 0, 86400] as $delta) {
            $o = $tz->getOffset(new DateTimeImmutable('@'.($base + $delta)));
            if ($tz->getOffset(new DateTimeImmutable('@'.($base - $o))) === $o) {
                $found[$o] = sprintf('%s%02d:%02d', $o < 0 ? '-' : '+', intdiv(abs($o), 3600), intdiv(abs($o) % 3600, 60));
            }
        }

        return count($found) > 1 ? array_values($found) : [];
    }

    /** True when the local time is a valid, unambiguous instant. */
    public function isUnambiguous(string $local, string $timezone): bool
    {
        try {
            $this->localToUtc($local, $timezone);

            return true;
        } catch (InvalidCalendarDate) {
            return false;
        }
    }

    public function toLocal(DateTimeImmutable $instant, string $timezone): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone($timezone));
    }

    /** Midnight of the given local date as an instant (midnight always exists in the supported zones; if it does not, the first valid instant of the day is used). */
    public function startOfDay(string $isoDate, string $timezone): DateTimeImmutable
    {
        return (new DateTimeImmutable($isoDate.' 00:00:00', new DateTimeZone($timezone)));
    }

    /**
     * Lenient conversion for recurrence expansion: a gap shifts forward (like RFC 5545 / PHP), a fold uses the first occurrence.
     * User input must use localToUtc() instead.
     */
    public function lenient(string $local, string $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable($local, new DateTimeZone($timezone));
    }

    private function offsetSeconds(string $offset): int
    {
        if ($offset === 'Z') {
            return 0;
        }
        if (! preg_match('/^([+-])(\d{2}):?(\d{2})$/', $offset, $m)) {
            throw new InvalidCalendarDate('Malformed offset.');
        }

        return ($m[1] === '-' ? -1 : 1) * ((int) $m[2] * 3600 + (int) $m[3] * 60);
    }
}
