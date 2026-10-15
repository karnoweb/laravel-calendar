<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Enums\RecurrenceScope;

final readonly class ActionCommand
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  string|null  $sourceKey  required for `create` (no reference yet)
     */
    public function __construct(
        public string $operationId,
        public CalendarAction $action,
        public ?EventReference $reference,
        public array $payload,
        public ?string $expectedVersion = null,
        public ?string $sourceKey = null,
        public ?RecurrenceScope $scope = null,
    ) {
        if (! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $operationId)) {
            throw new InvalidArgumentException('Invalid operation id.');
        }
        if ($action->requiresReference() && $reference === null) {
            throw new InvalidArgumentException('This action requires an event reference.');
        }
        if (! $action->requiresReference() && $sourceKey === null && $reference === null) {
            throw new InvalidArgumentException('Create requires a source key.');
        }
    }

    public function targetSourceKey(): string
    {
        return $this->reference?->sourceKey ?? (string) $this->sourceKey;
    }
}
