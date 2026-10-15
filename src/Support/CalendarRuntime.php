<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Illuminate\Contracts\Container\Container;
use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDefinition;
use Karnoweb\Calendar\Security\CalendarAuthorizationGuard;
use Karnoweb\Calendar\Security\ContextScopeGuard;

/**
 * Rebuilds (context, definition) from scratch for each request:
 * key allowlist -> ScopeResolver -> Factory from the host container -> structural validation -> read guard.
 */
final class CalendarRuntime
{
    public function __construct(
        private readonly Container $container,
        private readonly CalendarRegistry $registry,
        private readonly ContextFactory $contexts,
        private readonly CalendarAuthorizationGuard $guard,
    ) {}

    /**
     * @throws CalendarAccessDenied unknown key, guests, scope mismatch
     * @throws InvalidCalendarDefinition broken host definition
     */
    public function resolve(string $calendarKey): ResolvedCalendar
    {
        if (! $this->registry->has($calendarKey)) {
            // Indistinguishable from "not allowed" on purpose.
            throw new CalendarAccessDenied('Unknown calendar.');
        }

        $context = $this->contexts->make($calendarKey);

        /** @var CalendarFactory $factory */
        $factory = $this->container->make($this->registry->factory($calendarKey));
        $definition = $factory->make($context);

        CalendarDefinitionValidator::validate($definition, checkViewsExist: true);
        ContextScopeGuard::assertMatches($definition, $context, $calendarKey);
        $this->guard->assertCanRead($definition, $context);

        return new ResolvedCalendar($definition, $context);
    }
}
