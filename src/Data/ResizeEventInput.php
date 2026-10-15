<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use DateTimeImmutable;
use InvalidArgumentException;
use Karnoweb\Calendar\Enums\RecurrenceScope;
use Karnoweb\Calendar\Exceptions\InvalidActionPayload;

/** Resize payload: the new `end` (start never changes). */
final readonly class ResizeEventInput
{
    public function __construct(
        public EventReference $reference,
        public DateTimeImmutable $end,
        public string $timezone,
        public ?string $expectedVersion = null,
        public ?RecurrenceScope $scope = null,
    ) {}

    public static function fromCommand(ActionCommand $command, string $fallbackTimezone = 'UTC'): self
    {
        $p = $command->payload;
        $timezone = is_string($p['timezone'] ?? null) && in_array($p['timezone'], timezone_identifiers_list(), true) ? $p['timezone'] : $fallbackTimezone;
        try {
            $end = CreateEventInput::parseInstant($p['end'] ?? null, false, $timezone);
        } catch (InvalidArgumentException) {
            throw new InvalidActionPayload(['end' => 'calendar::calendar.validation.date_invalid']);
        }

        return new self(
            $command->reference ?? throw new InvalidArgumentException('Reference required.'),
            $end, $timezone, $command->expectedVersion, $command->scope,
        );
    }
}
