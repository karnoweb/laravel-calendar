<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Karnoweb\Calendar\Exceptions\InvalidActionPayload;
use Karnoweb\Calendar\Security\CalendarInputNormalizer;
use Karnoweb\Calendar\Security\SafeCalendarMarkup;

/**
 * Typed, validated form of a create/update payload. Host handlers should use this instead of
 * reading ActionCommand::$payload directly.
 *
 * Payload keys: title, description?, location?, category?, status?, allDay, start, end, timezone?,
 * recurrence? (array), extra? (array<string, scalar>). Timed values are ISO-8601 WITH offset;
 * all-day values are Y-m-d with an EXCLUSIVE end date.
 */
final readonly class CreateEventInput
{
    /** @param array<string, scalar|null> $extra */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $location,
        public ?string $category,
        public ?string $status,
        public bool $allDay,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public string $timezone,
        public ?RecurrenceRuleData $recurrence = null,
        public array $extra = [],
    ) {}

    public static function fromCommand(ActionCommand $command, string $fallbackTimezone = 'UTC'): self
    {
        return self::fromPayload($command->payload, $fallbackTimezone);
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload, string $fallbackTimezone = 'UTC'): self
    {
        $errors = [];
        $title = SafeCalendarMarkup::text(is_string($payload['title'] ?? null) ? $payload['title'] : '', 191);
        if ($title === '') {
            $errors['title'] = 'calendar::calendar.validation.title_required';
        }

        $timezone = is_string($payload['timezone'] ?? null) ? $payload['timezone'] : $fallbackTimezone;
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $errors['timezone'] = 'calendar::calendar.validation.timezone_invalid';
            $timezone = $fallbackTimezone;
        }
        $allDay = (bool) ($payload['allDay'] ?? false);

        $start = $end = null;
        try {
            $start = self::parseInstant($payload['start'] ?? null, $allDay, $timezone);
        } catch (InvalidArgumentException) {
            $errors['start'] = 'calendar::calendar.validation.date_invalid';
        }
        try {
            $end = self::parseInstant($payload['end'] ?? null, $allDay, $timezone);
        } catch (InvalidArgumentException) {
            $errors['end'] = 'calendar::calendar.validation.date_invalid';
        }
        if ($start !== null && $end !== null) {
            $ok = $allDay ? $end->format('Y-m-d') > $start->format('Y-m-d') : $end > $start;
            if (! $ok) {
                $errors['end'] = 'calendar::calendar.validation.end_after_start';
            }
        }

        $recurrence = null;
        if (isset($payload['recurrence']) && is_array($payload['recurrence']) && $payload['recurrence'] !== []) {
            try {
                $recurrence = RecurrenceRuleData::fromArray($payload['recurrence'], $timezone);
            } catch (InvalidArgumentException) {
                $errors['recurrence'] = 'calendar::calendar.validation.recurrence_invalid';
            }
        }

        $extra = [];
        foreach ((array) ($payload['extra'] ?? []) as $k => $v) {
            if (is_string($k) && preg_match('/^[a-z][a-z0-9_]{0,39}$/', $k) && (is_scalar($v) || $v === null)) {
                $extra[$k] = is_string($v) ? SafeCalendarMarkup::text($v, 500) : $v;
            }
        }

        if ($errors !== [] || $start === null || $end === null) {
            throw new InvalidActionPayload($errors);
        }

        return new self(
            $title,
            self::nullable($payload['description'] ?? null, true),
            self::nullable($payload['location'] ?? null),
            self::nullable($payload['category'] ?? null),
            self::nullable($payload['status'] ?? null),
            $allDay,
            $start,
            $end,
            $timezone,
            $recurrence,
            $extra,
        );
    }

    /** @throws InvalidArgumentException */
    public static function parseInstant(mixed $value, bool $allDay, string $timezone): DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('Missing date.');
        }
        if ($allDay) {
            $date = CalendarInputNormalizer::gregorianDate($value);

            return new DateTimeImmutable($date.' 00:00:00', new DateTimeZone($timezone));
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/', $value)) {
            throw new InvalidArgumentException('Timed values require an explicit offset.');
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            throw new InvalidArgumentException('Invalid datetime.');
        }
    }

    private static function nullable(mixed $value, bool $multiline = false): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $clean = $multiline ? SafeCalendarMarkup::multiline($value) : SafeCalendarMarkup::text($value, 191);

        return $clean === '' ? null : $clean;
    }

    /** @return array<string, mixed> payload form (round-trips through fromPayload) */
    public function toPayload(): array
    {
        $utc = new DateTimeZone('UTC');

        return [
            'title' => $this->title, 'description' => $this->description, 'location' => $this->location,
            'category' => $this->category, 'status' => $this->status, 'allDay' => $this->allDay,
            'start' => $this->allDay ? $this->start->format('Y-m-d') : $this->start->setTimezone($utc)->format('c'),
            'end' => $this->allDay ? $this->end->format('Y-m-d') : $this->end->setTimezone($utc)->format('c'),
            'timezone' => $this->timezone, 'recurrence' => $this->recurrence?->toArray(), 'extra' => $this->extra,
        ];
    }
}
