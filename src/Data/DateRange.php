<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Half-open range [start, end). Instants are normalised to UTC for transport to providers;
 * `timezone` tells how "days" inside the range must be interpreted.
 */
final readonly class DateRange
{
    public DateTimeImmutable $startInclusive;

    public DateTimeImmutable $endExclusive;

    public function __construct(
        DateTimeImmutable $startInclusive,
        DateTimeImmutable $endExclusive,
        public string $timezone,
    ) {
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Invalid range timezone.');
        }
        $utc = new DateTimeZone('UTC');
        $this->startInclusive = $startInclusive->setTimezone($utc);
        $this->endExclusive = $endExclusive->setTimezone($utc);
        if ($this->endExclusive <= $this->startInclusive) {
            throw new InvalidArgumentException('Range end must follow start.');
        }
    }

    /** Whole local days: [startDate 00:00, endDate 00:00) in the given timezone. */
    public static function ofLocalDays(string $startDate, string $endDateExclusive, string $timezone): self
    {
        $tz = new DateTimeZone($timezone);

        return new self(
            new DateTimeImmutable($startDate.' 00:00:00', $tz),
            new DateTimeImmutable($endDateExclusive.' 00:00:00', $tz),
            $timezone,
        );
    }

    public function days(): int
    {
        $tz = new DateTimeZone($this->timezone);
        $first = $this->startInclusive->setTimezone($tz)->format('Y-m-d');
        $last = $this->endExclusive->modify('-1 second')->setTimezone($tz)->format('Y-m-d');
        $utc = new DateTimeZone('UTC');

        return (int) (new DateTimeImmutable($first, $utc))->diff(new DateTimeImmutable($last, $utc))->format('%a') + 1;
    }

    /** Overlap test for half-open intervals: a.start < range.end && a.end > range.start. */
    public function overlaps(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return $start < $this->endExclusive && $end > $this->startInclusive;
    }

    public function signature(): string
    {
        return $this->startInclusive->format('Uu').'|'.$this->endExclusive->format('Uu').'|'.$this->timezone;
    }
}
