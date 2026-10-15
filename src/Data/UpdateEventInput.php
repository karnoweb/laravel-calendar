<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use Karnoweb\Calendar\Enums\RecurrenceScope;

/** Update payload (same fields as create) bound to a reference, expected version and recurrence scope. */
final readonly class UpdateEventInput
{
    public function __construct(
        public EventReference $reference,
        public CreateEventInput $fields,
        public ?string $expectedVersion = null,
        public ?RecurrenceScope $scope = null,
        public string $mode = 'update',
    ) {}

    public static function fromCommand(ActionCommand $command, string $fallbackTimezone = 'UTC'): self
    {
        $mode = (string) ($command->payload['mode'] ?? 'update');

        return new self(
            $command->reference ?? throw new \InvalidArgumentException('Reference required.'),
            CreateEventInput::fromPayload($command->payload, $fallbackTimezone),
            $command->expectedVersion,
            $command->scope,
            in_array($mode, ['update', 'delete'], true) ? $mode : 'update',
        );
    }
}
