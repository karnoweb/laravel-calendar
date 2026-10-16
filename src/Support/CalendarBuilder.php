<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Actions\EloquentActionHandler;
use Karnoweb\Calendar\Contracts\ActionHandler;
use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarFilterData;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Data\CalendarViews;
use Karnoweb\Calendar\Data\EventSourceData;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDefinition;
use Karnoweb\Calendar\Providers\EloquentEventProvider;

/**
 * Fluent builder that produces an immutable CalendarDefinition. A fresh builder is returned by
 * Calendar::make() every time, so no state is shared between calendars or requests.
 */
final class CalendarBuilder
{
    private CalendarModelRegistry $modelRegistry;

    private ?string $key = null;

    /** @var array<string, class-string<EventProvider>> */
    private array $sources = [];

    /** @var array<string, EventSourceData> */
    private array $sourceData = [];

    /** @var class-string<ActionHandler>|null */
    private ?string $handlerClass = null;

    private bool $autoHandler = false;

    private ?CalendarSettings $settings = null;

    private ?CalendarViews $views = null;

    /** @var list<CalendarFilterData> */
    private array $filters = [];

    /** @var list<CalendarFilterData> */
    private array $modelFilters = [];

    public function __construct(?CalendarModelRegistry $models = null)
    {
        $this->modelRegistry = $models ?? (app()->bound(CalendarModelRegistry::class)
            ? app(CalendarModelRegistry::class)
            : new CalendarModelRegistry);
    }

    public function key(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Each entry is either a provider class-string, a registered model source key, or an array:
     * ['provider' => Class::class, 'label' => ..., 'color' => 'blue', 'readOnly' => bool, 'visible' => bool].
     *
     * @param  array<int|string, class-string<EventProvider>|string|array<string, mixed>>  $sources
     */
    public function sources(array $sources): self
    {
        $this->sources = [];
        $this->sourceData = [];
        $this->modelFilters = [];
        if ($this->autoHandler) {
            $this->handlerClass = null;
            $this->autoHandler = false;
        }
        foreach ($sources as $key => $declaration) {
            if (is_int($key) && is_string($declaration)) {
                $this->addModelSource($declaration);
            } else {
                $this->addSource((string) $key, $declaration);
            }
        }

        return $this;
    }

    /**
     * Attach registered Eloquent model sources from CalendarModelRegistry.
     * - `null` => all registered model sources.
     * - `['tasks', 'classes']` => only those model sources.
     * - `[]` => no sources (never falls back to "all").
     *
     * @param  array<int|string, string|array<string, mixed>>|null  $keys
     */
    public function modelSources(?array $keys = null): self
    {
        $this->sources = [];
        $this->sourceData = [];
        $this->modelFilters = [];
        if ($this->autoHandler) {
            $this->handlerClass = null;
            $this->autoHandler = false;
        }

        if ($keys === null) {
            foreach ($this->modelRegistry->keys() as $registeredKey) {
                $this->addModelSource($registeredKey);
            }

            return $this;
        }

        if ($keys === []) {
            return $this;
        }

        foreach ($keys as $k => $v) {
            if (is_int($k) && is_string($v)) {
                $this->addModelSource($v);
            } elseif (is_string($k) && is_array($v)) {
                $this->addModelSource($k, $v);
            } else {
                throw new InvalidCalendarDefinition('Invalid model source declaration.');
            }
        }

        return $this;
    }

    /**
     * Add one registered model source by its key in CalendarModelRegistry, with optional per-calendar overrides.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function addModelSource(string $key, array $overrides = []): self
    {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $key)) {
            throw new InvalidCalendarDefinition('Invalid event source key.');
        }
        if (isset($this->sources[$key])) {
            throw new InvalidCalendarDefinition("Duplicate event source [{$key}].");
        }
        if (! $this->modelRegistry->has($key)) {
            throw new InvalidCalendarDefinition("Unknown registered calendar model source [{$key}].");
        }

        $def = $this->modelRegistry->definition($key);
        $provider = $overrides['provider'] ?? $def->providerClass ?? EloquentEventProvider::class;
        $readOnly = array_key_exists('readOnly', $overrides) ? (bool) $overrides['readOnly'] : $def->readOnly;
        $capabilities = $overrides['capabilities'] ?? ($readOnly ? ActionCapabilities::none() : $def->capabilities);
        $label = (string) ($overrides['label'] ?? $def->resolveLabel(null, $key));
        $color = (string) ($overrides['color'] ?? $def->color);
        $visible = array_key_exists('visible', $overrides) ? (bool) $overrides['visible'] : true;

        $this->addSource($key, [
            'provider' => $provider,
            'label' => $label,
            'color' => $color,
            'readOnly' => $readOnly,
            'capabilities' => $capabilities,
            'visible' => $visible,
        ]);

        foreach ($def->buildFilters($key) as $filter) {
            $this->modelFilters[] = $filter;
        }

        if (! $readOnly && $this->handlerClass === null) {
            $this->handlerClass = EloquentActionHandler::class;
            $this->autoHandler = true;
        }

        return $this;
    }

    /** @param class-string<EventProvider>|string|array<string, mixed> $declaration */
    public function addSource(string $key, string|array $declaration): self
    {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $key)) {
            throw new InvalidCalendarDefinition('Invalid event source key.');
        }
        if (isset($this->sources[$key])) {
            throw new InvalidCalendarDefinition("Duplicate event source [{$key}].");
        }

        if (is_array($declaration) && ! isset($declaration['provider']) && $this->modelRegistry->has($key)) {
            return $this->addModelSource($key, $declaration);
        }

        $options = is_array($declaration) ? $declaration : ['provider' => $declaration];
        $provider = $options['provider'] ?? null;
        if (! is_string($provider) || ! is_subclass_of($provider, EventProvider::class)) {
            throw new InvalidCalendarDefinition("Source [{$key}] provider must implement EventProvider.");
        }
        try {
            $data = new EventSourceData(
                key: $key,
                label: (string) ($options['label'] ?? $key),
                color: (string) ($options['color'] ?? 'blue'),
                readOnly: (bool) ($options['readOnly'] ?? false),
                capabilities: $options['capabilities'] ?? null,
                visible: (bool) ($options['visible'] ?? true),
            );
        } catch (\InvalidArgumentException $e) {
            throw new InvalidCalendarDefinition("Source [{$key}]: ".$e->getMessage(), 0, $e);
        }
        $this->sources[$key] = $provider;
        $this->sourceData[$key] = $data;

        return $this;
    }

    /** Google Calendar source (key "google"). Pass false to leave it out, e.g. ->google(config('app.google')). */
    public function google(bool $enabled = true, string $label = 'Google Calendar'): self
    {
        return $enabled ? $this->addSource('google', ['provider' => \Karnoweb\Calendar\Google\GoogleCalendarProvider::class, 'label' => $label, 'color' => '#1a73e8']) : $this;
    }

    /** Official holidays source (key "holidays", read-only, data from config('calendar.holidays')). */
    public function holidays(bool $enabled = true, string $label = 'Official holidays'): self
    {
        return $enabled ? $this->addSource('holidays', ['provider' => \Karnoweb\Calendar\Holidays\HolidayProvider::class, 'label' => $label, 'color' => '#d93025', 'readOnly' => true]) : $this;
    }

    /** @param class-string<ActionHandler> $handlerClass */
    public function actions(string $handlerClass): self
    {
        if (! is_subclass_of($handlerClass, ActionHandler::class)) {
            throw new InvalidCalendarDefinition('Action handler does not implement ActionHandler.');
        }
        $this->handlerClass = $handlerClass;
        $this->autoHandler = false;

        return $this;
    }

    public function settings(CalendarSettings $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    public function views(CalendarViews $views): self
    {
        $this->views = $views;

        return $this;
    }

    /** @param list<CalendarFilterData> $filters */
    public function filters(array $filters): self
    {
        foreach ($filters as $filter) {
            if (! $filter instanceof CalendarFilterData) {
                throw new InvalidCalendarDefinition('Filters must be CalendarFilterData instances.');
            }
        }
        $this->filters = array_values($filters);

        return $this;
    }

    public function build(): CalendarDefinition
    {
        if ($this->key === null || ! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $this->key)) {
            throw new InvalidCalendarDefinition('Invalid calendar key.');
        }
        if ($this->sources === []) {
            throw new InvalidCalendarDefinition('Calendar requires at least one event source.');
        }

        $mergedFilters = $this->filters;
        $seenFilterKeys = [];
        foreach ($mergedFilters as $f) {
            $seenFilterKeys[$f->key()] = true;
        }
        foreach ($this->modelFilters as $mf) {
            if (isset($this->sources[$mf->sourceKey]) && ! isset($seenFilterKeys[$mf->key()])) {
                $mergedFilters[] = $mf;
                $seenFilterKeys[$mf->key()] = true;
            }
        }

        $definition = new CalendarDefinition(
            $this->key,
            $this->sources,
            $this->handlerClass,
            $this->settings ?? new CalendarSettings,
            $this->views,
            $this->sourceData,
            $mergedFilters,
        );

        CalendarDefinitionValidator::validate($definition);

        return $definition;
    }
}
