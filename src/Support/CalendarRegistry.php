<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDefinition;

/**
 * Allowlist: calendar key => Factory CLASS (never a closure or instance). The factory is
 * re-instantiated from the container on every request, so no per-user state survives.
 */
final class CalendarRegistry
{
    /** @var array<string, class-string<CalendarFactory>> */
    private array $factories = [];

    /** @param class-string<CalendarFactory> $factoryClass */
    public function register(string $key, string $factoryClass): void
    {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $key)) {
            throw new InvalidCalendarDefinition('Invalid calendar key.');
        }
        if (isset($this->factories[$key])) {
            throw new InvalidCalendarDefinition("Calendar key [{$key}] already registered.");
        }
        if (! is_string($factoryClass) || ! is_subclass_of($factoryClass, CalendarFactory::class)) {
            throw new InvalidCalendarDefinition('Factory must be a class implementing CalendarFactory.');
        }
        $this->factories[$key] = $factoryClass;
    }

    public function has(string $key): bool
    {
        return isset($this->factories[$key]);
    }

    /** @return class-string<CalendarFactory> */
    public function factory(string $key): string
    {
        return $this->factories[$key] ?? throw new InvalidCalendarDefinition('Unknown calendar key.');
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->factories);
    }
}
