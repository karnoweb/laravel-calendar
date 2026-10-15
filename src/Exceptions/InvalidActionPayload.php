<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Exceptions;

use InvalidArgumentException;

/** Payload failed validation. Carries field errors keyed by field name (translation keys or plain text). */
final class InvalidActionPayload extends InvalidArgumentException
{
    /** @param array<string, list<string>|string> $fieldErrors */
    public function __construct(public readonly array $fieldErrors, string $message = 'Invalid action payload.')
    {
        parent::__construct($message);
    }
}
