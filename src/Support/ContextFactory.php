<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Illuminate\Contracts\Container\Container;
use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Contracts\TimezoneResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Throwable;

/** Builds the server-side context on EVERY request; nothing is cached between requests. */
final class ContextFactory
{
    public function __construct(private readonly Container $container) {}

    /** @throws CalendarAccessDenied */
    public function make(string $calendarKey): CalendarContext
    {
        try {
            /** @var ScopeResolver $resolver */
            $resolver = $this->container->make(ScopeResolver::class);
            $context = $resolver->resolve($calendarKey);
        } catch (CalendarAccessDenied $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw new CalendarAccessDenied;
            }
            throw $e;
        }
        if ($context->calendarKey !== $calendarKey) {
            throw new CalendarAccessDenied('Scope was resolved for a different calendar.');
        }

        /** @var TimezoneResolver $tz */
        $tz = $this->container->make(TimezoneResolver::class);

        return $context->withTimezone($tz->resolve($context));
    }
}
