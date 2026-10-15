<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\RecurrenceFrequency;

/**
 * Weekday numbers follow 0 = Sunday ... 6 = Saturday (same convention as config/calendar.php).
 * Neither `until` nor `count` is allowed to be set together; both null means "no end" (expansion
 * is still bounded by the query range and RecurrenceLimitPolicy).
 */
final readonly class RecurrenceRuleData
{
    public const MAX_COUNT = 5000;

    /** @var list<int> */
    public array $byWeekday;

    /** @param list<int> $byWeekday */
    public function __construct(
        public RecurrenceFrequency $frequency,
        public int $interval = 1,
        array $byWeekday = [],
        public ?int $monthDay = null,
        public ?DateTimeImmutable $until = null,
        public ?int $count = null,
        public CalendarSystem $calendarSystem = CalendarSystem::Gregorian,
        public string $timezone = 'UTC',
    ) {
        if ($interval < 1 || $interval > 999) {
            throw new InvalidArgumentException('Recurrence interval must be 1..999.');
        }
        if ($until !== null && $count !== null) {
            throw new InvalidArgumentException('Recurrence cannot have both until and count.');
        }
        if ($count !== null && ($count < 1 || $count > self::MAX_COUNT)) {
            throw new InvalidArgumentException('Recurrence count out of range.');
        }
        if ($monthDay !== null && ($monthDay < 1 || $monthDay > 31)) {
            throw new InvalidArgumentException('Recurrence month day must be 1..31.');
        }
        if ($monthDay !== null && ! in_array($frequency, [RecurrenceFrequency::Monthly, RecurrenceFrequency::Yearly], true)) {
            throw new InvalidArgumentException('monthDay only applies to monthly/yearly rules.');
        }
        $days = [];
        foreach ($byWeekday as $d) {
            if (! is_int($d) || $d < 0 || $d > 6) {
                throw new InvalidArgumentException('Weekday must be 0..6.');
            }
            $days[$d] = $d;
        }
        if ($days !== [] && $frequency !== RecurrenceFrequency::Weekly) {
            throw new InvalidArgumentException('byWeekday only applies to weekly rules.');
        }
        ksort($days);
        $this->byWeekday = array_values($days);
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Invalid recurrence timezone.');
        }
    }

    public function withCount(?int $count): self
    {
        return new self($this->frequency, $this->interval, $this->byWeekday, $this->monthDay, null, $count, $this->calendarSystem, $this->timezone);
    }

    public function withUntil(?DateTimeImmutable $until): self
    {
        return new self($this->frequency, $this->interval, $this->byWeekday, $this->monthDay, $until, null, $this->calendarSystem, $this->timezone);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'frequency' => $this->frequency->value,
            'interval' => $this->interval,
            'byWeekday' => $this->byWeekday,
            'monthDay' => $this->monthDay,
            'until' => $this->until?->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'count' => $this->count,
            'calendarSystem' => $this->calendarSystem->value,
            'timezone' => $this->timezone,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, string $defaultTimezone = 'UTC'): self
    {
        $frequency = RecurrenceFrequency::tryFrom(strtoupper((string) ($data['frequency'] ?? '')));
        if ($frequency === null) {
            throw new InvalidArgumentException('Invalid recurrence frequency.');
        }
        $until = null;
        if (! empty($data['until'])) {
            try {
                $until = new DateTimeImmutable((string) $data['until']);
            } catch (\Exception) {
                throw new InvalidArgumentException('Invalid recurrence until date.');
            }
        }
        $system = CalendarSystem::tryFrom((string) ($data['calendarSystem'] ?? 'gregorian')) ?? CalendarSystem::Gregorian;

        return new self(
            $frequency,
            (int) ($data['interval'] ?? 1),
            array_map('intval', (array) ($data['byWeekday'] ?? [])),
            isset($data['monthDay']) && $data['monthDay'] !== '' ? (int) $data['monthDay'] : null,
            $until,
            isset($data['count']) && $data['count'] !== '' ? (int) $data['count'] : null,
            $system,
            (string) ($data['timezone'] ?? $defaultTimezone),
        );
    }
}
