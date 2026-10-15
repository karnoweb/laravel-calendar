<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Contracts\CalendarFactory;

/** Facade target. Holds only the registry; `make()` never shares state. */
final class CalendarManager
{
    public function __construct(private readonly CalendarRegistry $registry) {}

    /** @param class-string<CalendarFactory> $factoryClass */
    public function register(string $key, string $factoryClass): void
    {
        $this->registry->register($key, $factoryClass);
    }

    public function make(): CalendarBuilder
    {
        return new CalendarBuilder;
    }

    public function registry(): CalendarRegistry
    {
        return $this->registry;
    }
}
