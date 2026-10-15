<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Security;

use Illuminate\Contracts\Container\Container;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Exceptions\CalendarNotFound;
use Karnoweb\Calendar\Support\SourceRegistry;

/**
 * A reference from the browser is only a claim. It is re-fetched through the provider under the
 * server-resolved context; having an eventId never implies ownership.
 */
final class EventReferenceGuard
{
    public function __construct(private readonly Container $container, private readonly CalendarAuthorizationGuard $guard) {}

    /**
     * @throws CalendarAccessDenied unknown source / calendar not readable / provider mismatch
     * @throws CalendarNotFound provider returned nothing (missing OR not visible: indistinguishable on purpose)
     */
    public function resolve(CalendarDefinition $definition, CalendarContext $context, EventReference $reference): CalendarEventData
    {
        $this->guard->assertCanReadSource($definition, $context, $reference->sourceKey);

        $provider = (new SourceRegistry($definition, $this->container))->provider($reference->sourceKey);
        $event = $provider->find($reference, $context);

        if ($event === null) {
            throw new CalendarNotFound;
        }
        if ($event->reference->sourceKey !== $reference->sourceKey
            || $event->reference->eventId !== $reference->eventId
            || $event->reference->occurrenceKey !== $reference->occurrenceKey) {
            // Provider answered with a different identity: treat as forged/misrouted.
            throw new CalendarAccessDenied;
        }

        return $event;
    }
}
