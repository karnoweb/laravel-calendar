<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;

/**
 * One exception to a series. `originalOccurrenceKey` is the stable identity of the occurrence
 * (its ORIGINAL local start in the series timezone) and never changes when the occurrence is moved.
 */
final readonly class RecurrenceExceptionData
{
    public const CANCELLED = 'cancelled';

    public const REPLACED = 'replaced';

    /**
     * @param  array<string, mixed>|null  $replacement  keys: start, end (ISO-8601), optional title/description/location/...
     */
    public function __construct(
        public string $originalOccurrenceKey,
        public string $kind,
        public ?array $replacement = null,
    ) {
        if ($originalOccurrenceKey === '' || strlen($originalOccurrenceKey) > 64) {
            throw new InvalidArgumentException('Invalid original occurrence key.');
        }
        if (! in_array($kind, [self::CANCELLED, self::REPLACED], true)) {
            throw new InvalidArgumentException('Invalid exception kind.');
        }
        if ($kind === self::REPLACED && (! isset($replacement['start']) || ! isset($replacement['end']))) {
            throw new InvalidArgumentException('A replaced occurrence needs start and end.');
        }
        if ($kind === self::CANCELLED && $replacement !== null) {
            throw new InvalidArgumentException('A cancelled occurrence has no replacement.');
        }
    }

    public function isCancelled(): bool
    {
        return $this->kind === self::CANCELLED;
    }
}
