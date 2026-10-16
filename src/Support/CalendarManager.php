<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Contracts\CalendarFactory;

/** Facade target. Holds only the registries; `make()` never shares state. */
final class CalendarManager
{
    private CalendarModelRegistry $modelRegistry;

    public function __construct(
        private readonly CalendarRegistry $registry,
        ?CalendarModelRegistry $models = null,
    ) {
        $this->modelRegistry = $models ?? app(CalendarModelRegistry::class);
    }

    /** @param class-string<CalendarFactory> $factoryClass */
    public function register(string $key, string $factoryClass): void
    {
        $this->registry->register($key, $factoryClass);
    }

    /**
     * Register an Eloquent model using HasCalendarEvents as a calendar event source.
     *
     * @param  string|class-string<Model>  $sourceKeyOrModel
     * @param  class-string<Model>|null  $modelClass
     */
    public function registerModel(string $sourceKeyOrModel, ?string $modelClass = null): string
    {
        return $this->modelRegistry->register($sourceKeyOrModel, $modelClass);
    }

    /** @param array<int|string, class-string<Model>> $models */
    public function registerModels(array $models): void
    {
        $this->modelRegistry->registerMany($models);
    }

    public function make(): CalendarBuilder
    {
        return new CalendarBuilder($this->modelRegistry);
    }

    public function registry(): CalendarRegistry
    {
        return $this->registry;
    }

    public function models(): CalendarModelRegistry
    {
        return $this->modelRegistry;
    }
}
