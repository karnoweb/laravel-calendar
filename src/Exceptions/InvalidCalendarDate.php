<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Exceptions;

use InvalidArgumentException;

/** Non-existent (DST gap), ambiguous (DST fold) or malformed local date/time. */
final class InvalidCalendarDate extends InvalidArgumentException
{
    public const GAP = 'gap';

    public const AMBIGUOUS = 'ambiguous';

    public const MALFORMED = 'malformed';

    public function __construct(string $message, public readonly string $reason = self::MALFORMED)
    {
        parent::__construct($message);
    }
}
