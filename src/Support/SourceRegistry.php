<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Illuminate\Contracts\Container\Container;
use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Providers\EloquentEventProvider;

/**
 * Resolves providers for ONE definition. Providers are built fresh from the host container, and an
 * unknown source key fails closed (no provider is ever derived from client input).
 */
final class SourceRegistry
{
    public function __construct(private readonly CalendarDefinition $definition, private readonly Container $container) {}

    public function has(string $sourceKey): bool
    {
        return $this->definition->hasSource($sourceKey);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return $this->definition->sourceKeys();
    }

    public function provider(string $sourceKey): EventProvider
    {
        if (! $this->has($sourceKey)) {
            throw new CalendarAccessDenied('Unknown calendar source.');
        }

        $class = $this->definition->sources[$sourceKey];
        if (is_a($class, EloquentEventProvider::class, true)) {
            return $this->container->make($class, ['sourceKey' => $sourceKey]);
        }

        return $this->container->make($class);
    }
}
