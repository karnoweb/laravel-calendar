<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;
use Karnoweb\Calendar\Enums\RecurrenceScope;

/** Delete one event, or (recurring) one occurrence / this-and-future / the whole series. */
final readonly class DeleteEventInput
{
    public function __construct(
        public EventReference $reference,
        public RecurrenceScope $scope = RecurrenceScope::All,
        public ?string $expectedVersion = null,
    ) {}

    public static function fromCommand(ActionCommand $command): self
    {
        return new self(
            $command->reference ?? throw new InvalidArgumentException('Reference required.'),
            $command->scope ?? RecurrenceScope::All,
            $command->expectedVersion,
        );
    }
}
