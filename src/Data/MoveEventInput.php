<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use DateTimeImmutable;
use InvalidArgumentException;
use Karnoweb\Calendar\Enums\RecurrenceScope;
use Karnoweb\Calendar\Exceptions\InvalidActionPayload;

/**
 * Move payload: mode ('date' keeps the local wall clock, 'time' sets a new time slot), start, end,
 * allDay, timezone. The server (Livewire layer) computes start/end via TimeMovePolicy; the handler
 * gets final instants.
 */
final readonly class MoveEventInput
{
    public function __construct(
        public EventReference $reference,
        public string $mode,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public bool $allDay,
        public string $timezone,
        public ?string $expectedVersion = null,
        public ?RecurrenceScope $scope = null,
    ) {}

    public static function fromCommand(ActionCommand $command, string $fallbackTimezone = 'UTC'): self
    {
        $p = $command->payload;
        $timezone = is_string($p['timezone'] ?? null) && in_array($p['timezone'], timezone_identifiers_list(), true) ? $p['timezone'] : $fallbackTimezone;
        $allDay = (bool) ($p['allDay'] ?? false);
        $mode = in_array($p['mode'] ?? 'date', ['date', 'time'], true) ? (string) ($p['mode'] ?? 'date') : 'date';
        try {
            $start = CreateEventInput::parseInstant($p['start'] ?? null, $allDay, $timezone);
            $end = CreateEventInput::parseInstant($p['end'] ?? null, $allDay, $timezone);
        } catch (InvalidArgumentException) {
            throw new InvalidActionPayload(['start' => 'calendar::calendar.validation.date_invalid']);
        }
        if ($allDay ? $end->format('Y-m-d') <= $start->format('Y-m-d') : $end <= $start) {
            throw new InvalidActionPayload(['end' => 'calendar::calendar.validation.end_after_start']);
        }

        return new self(
            $command->reference ?? throw new InvalidArgumentException('Reference required.'),
            $mode, $start, $end, $allDay, $timezone, $command->expectedVersion, $command->scope,
        );
    }
}
