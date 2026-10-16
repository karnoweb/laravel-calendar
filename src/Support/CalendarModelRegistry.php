<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\CalendarModelDefinition;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDefinition;
use Throwable;

/**
 * Server-side allowlist of Eloquent models participating as calendar event sources.
 * Never scans the filesystem; only explicitly registered models with HasCalendarEvents are accepted.
 */
final class CalendarModelRegistry
{
    /** @var array<string, class-string<Model>> */
    private array $models = [];

    /**
     * Register a model source. Accepts either `register('tasks', Task::class)` or `register(Task::class)`.
     *
     * @param  string|class-string<Model>  $sourceKeyOrModel
     * @param  class-string<Model>|null  $modelClass
     */
    public function register(string $sourceKeyOrModel, ?string $modelClass = null): string
    {
        if ($modelClass === null) {
            $modelClass = $sourceKeyOrModel;
            $explicitKey = null;
        } else {
            $explicitKey = $sourceKeyOrModel;
        }

        if (! is_string($modelClass) || $modelClass === '' || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidCalendarDefinition("Calendar model [{$modelClass}] must be a valid Eloquent Model class.");
        }

        $traits = class_uses_recursive($modelClass);
        if (! in_array(HasCalendarEvents::class, $traits, true)) {
            throw new InvalidCalendarDefinition("Model [{$modelClass}] must use the HasCalendarEvents trait.");
        }

        try {
            $definition = $this->resolveRawDefinition($modelClass);
        } catch (InvalidCalendarDefinition $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidCalendarDefinition("Model [{$modelClass}] returned an invalid calendarDefinition(): ".$e->getMessage(), 0, $e);
        }

        $sourceKey = $explicitKey ?? $definition->sourceKey;
        if (! is_string($sourceKey) || ! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $sourceKey)) {
            throw new InvalidCalendarDefinition("Invalid model calendar source key [{$sourceKey}].");
        }

        if (isset($this->models[$sourceKey])) {
            throw new InvalidCalendarDefinition("Calendar model source [{$sourceKey}] is already registered.");
        }

        $this->models[$sourceKey] = $modelClass;

        return $sourceKey;
    }

    /**
     * @param  array<int|string, class-string<Model>>  $models
     */
    public function registerMany(array $models): void
    {
        foreach ($models as $key => $modelClass) {
            if (is_int($key)) {
                $this->register($modelClass);
            } else {
                $this->register($key, $modelClass);
            }
        }
    }

    public function has(string $sourceKey): bool
    {
        return isset($this->models[$sourceKey]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->models);
    }

    /** @return class-string<Model> */
    public function modelClass(string $sourceKey): string
    {
        if (! isset($this->models[$sourceKey])) {
            throw new InvalidCalendarDefinition("Unknown registered calendar model source [{$sourceKey}].");
        }

        return $this->models[$sourceKey];
    }

    public function definition(string $sourceKey): CalendarModelDefinition
    {
        $modelClass = $this->modelClass($sourceKey);

        return $this->resolveRawDefinition($modelClass)->withSourceKey($sourceKey);
    }

    public function forget(string $sourceKey): void
    {
        unset($this->models[$sourceKey]);
    }

    public function clear(): void
    {
        $this->models = [];
    }

    /** @param class-string<Model> $modelClass */
    private function resolveRawDefinition(string $modelClass): CalendarModelDefinition
    {
        if (! method_exists($modelClass, 'calendarDefinition')) {
            throw new InvalidCalendarDefinition("Model [{$modelClass}] does not define calendarDefinition().");
        }

        $ref = new \ReflectionMethod($modelClass, 'calendarDefinition');
        $def = $ref->isStatic()
            ? $modelClass::calendarDefinition()
            : (new $modelClass)->calendarDefinition();

        if (! $def instanceof CalendarModelDefinition) {
            throw new InvalidCalendarDefinition("Model [{$modelClass}]::calendarDefinition() must return CalendarModelDefinition.");
        }

        return $def;
    }
}
