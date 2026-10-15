<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Security\EventReferenceGuard;
use Throwable;

/** After a conflict the UI reloads authoritative data; the stale change is never re-applied automatically. */
final class CalendarConflictResolver
{
    public function __construct(private readonly EventReferenceGuard $references) {}

    public function freshEvent(CalendarDefinition $definition, CalendarContext $context, EventReference $reference): ?CalendarEventData
    {
        try {
            return $this->references->resolve($definition, $context, $reference);
        } catch (Throwable) {
            return null;
        }
    }
}
