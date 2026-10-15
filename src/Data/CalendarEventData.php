<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Karnoweb\Calendar\Security\AllowedCssToken;
use Karnoweb\Calendar\Security\SafeCalendarMarkup;

/**
 * One event (or one occurrence of a recurring series).
 *
 * Timed events: `start`/`end` are real instants (half-open [start, end)).
 * All-day events: only the DATE part of `start`/`end` (as given, no timezone conversion) is
 * meaningful and `end` is the EXCLUSIVE end date (10th..12th => covers the 10th and 11th).
 */
final readonly class CalendarEventData
{
    public string $title;

    public ?string $description;

    public ?string $location;

    public ?string $color;

    public ?string $url;

    public ?string $status;

    public ?string $category;

    public ActionCapabilities $capabilities;

    public function __construct(
        public EventReference $reference,
        string $title,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public bool $allDay = false,
        public ?string $version = null,
        ?ActionCapabilities $capabilities = null,
        ?string $description = null,
        ?string $location = null,
        ?string $color = null,
        ?string $url = null,
        ?string $status = null,
        ?string $category = null,
        public bool $isRecurring = false,
    ) {
        if ($allDay) {
            if ($end->format('Y-m-d') <= $start->format('Y-m-d')) {
                throw new InvalidArgumentException('All-day event exclusive end date must follow the start date.');
            }
        } elseif ($end <= $start) {
            throw new InvalidArgumentException('Event end must follow start.');
        }
        if ($color !== null && ! AllowedCssToken::isValidColor($color)) {
            throw new InvalidArgumentException('Event colour is not an allowed token.');
        }

        $this->title = SafeCalendarMarkup::text($title, 191);
        $this->description = $description === null ? null : SafeCalendarMarkup::multiline($description);
        $this->location = $location === null ? null : SafeCalendarMarkup::text($location, 191);
        $this->color = $color === null ? null : strtolower($color);
        $this->url = SafeCalendarMarkup::url($url);
        $this->status = $status === null ? null : SafeCalendarMarkup::text($status, 40);
        $this->category = $category === null ? null : SafeCalendarMarkup::text($category, 60);
        $this->capabilities = $capabilities ?? ActionCapabilities::none();
    }

    public function startAt(string $timezone): DateTimeImmutable
    {
        $tz = new DateTimeZone($timezone);

        return $this->allDay
            ? new DateTimeImmutable($this->start->format('Y-m-d').' 00:00:00', $tz)
            : $this->start->setTimezone($tz);
    }

    public function endAt(string $timezone): DateTimeImmutable
    {
        $tz = new DateTimeZone($timezone);

        return $this->allDay
            ? new DateTimeImmutable($this->end->format('Y-m-d').' 00:00:00', $tz)
            : $this->end->setTimezone($tz);
    }

    /** First local calendar date (Y-m-d) this event touches. */
    public function firstDate(string $timezone): string
    {
        return $this->startAt($timezone)->format('Y-m-d');
    }

    /** Last local calendar date (Y-m-d) this event touches (inclusive). */
    public function lastDate(string $timezone): string
    {
        return $this->endAt($timezone)->modify('-1 second')->format('Y-m-d');
    }

    public function isMultiDay(string $timezone): bool
    {
        return $this->lastDate($timezone) > $this->firstDate($timezone);
    }

    public function overlaps(DateRange $range): bool
    {
        return $range->overlaps($this->startAt($range->timezone), $this->endAt($range->timezone));
    }

    public function isReadOnly(): bool
    {
        return $this->capabilities->isReadOnly();
    }
}
