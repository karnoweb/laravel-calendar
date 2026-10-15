<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Security;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;

/** Ensures the resolved context and definition belong to the same calendar, and sources are real. */
final class ContextScopeGuard
{
    public static function assertMatches(CalendarDefinition $definition, CalendarContext $context, string $requestedKey): void
    {
        if ($definition->key !== $requestedKey || $context->calendarKey !== $requestedKey) {
            throw new CalendarAccessDenied('Calendar scope mismatch.');
        }
    }

    public static function assertSource(CalendarDefinition $definition, string $sourceKey): void
    {
        if (! $definition->hasSource($sourceKey)) {
            throw new CalendarAccessDenied('Unknown calendar source.');
        }
    }
}
