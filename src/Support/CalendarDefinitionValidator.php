<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Contracts\ActionHandler;
use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDefinition;

/** Static structural checks, applied by the builder and again by the runtime on every request. */
final class CalendarDefinitionValidator
{
    public static function validate(CalendarDefinition $definition, bool $checkViewsExist = false): void
    {
        if ($definition->sources === []) {
            throw new InvalidCalendarDefinition('Calendar requires at least one event source.');
        }
        foreach ($definition->sources as $key => $provider) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $key)) {
                throw new InvalidCalendarDefinition('Invalid event source key.');
            }
            if (! is_string($provider) || ! is_subclass_of($provider, EventProvider::class)) {
                throw new InvalidCalendarDefinition("Source [{$key}] provider must implement EventProvider.");
            }
        }
        foreach (array_keys($definition->sourceData) as $key) {
            if (! isset($definition->sources[$key])) {
                throw new InvalidCalendarDefinition("Source metadata for unknown source [{$key}].");
            }
        }
        if ($definition->handlerClass !== null && ! is_subclass_of($definition->handlerClass, ActionHandler::class)) {
            throw new InvalidCalendarDefinition('Action handler does not implement ActionHandler.');
        }
        $seen = [];
        foreach ($definition->filters as $filter) {
            if (! isset($definition->sources[$filter->sourceKey])) {
                throw new InvalidCalendarDefinition("Filter [{$filter->key()}] targets an unknown source.");
            }
            if (isset($seen[$filter->key()])) {
                throw new InvalidCalendarDefinition("Duplicate filter [{$filter->key()}].");
            }
            $seen[$filter->key()] = true;
        }
        if ($checkViewsExist) {
            foreach ($definition->views->all() as $slot => $name) {
                if (! view()->exists($name)) {
                    throw new InvalidCalendarDefinition("Configured view [{$name}] for [{$slot}] does not exist.");
                }
            }
        }
    }
}
