<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use Closure;
use InvalidArgumentException;
use Karnoweb\Calendar\Contracts\ActionHandler;
use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Security\AllowedCssToken;

/**
 * Type-level calendar configuration declared by an Eloquent model via HasCalendarEvents::calendarDefinition().
 * Constructed without querying the database; record-level callbacks run only on the current page's rows.
 */
final class CalendarModelDefinition
{
    public ActionCapabilities $capabilities;

    /**
     * @param  string|array<string, string>|null  $label
     * @param  string|Closure  $titleField
     * @param  string|Closure|null  $colorField
     * @param  string|Closure|null  $urlField
     * @param  list<string>  $with
     * @param  list<string>  $searchableFields
     * @param  array<string, string>  $statusOptions
     * @param  string|array<string, string>  $statusFilterLabel
     * @param  array<string, string>  $categoryOptions
     * @param  string|array<string, string>  $categoryFilterLabel
     * @param  array<string, string>  $statusColors
     * @param  array<string, string>  $categoryColors
     * @param  list<CalendarFilterData>  $filters
     * @param  array<string, Closure>  $filterCallbacks
     * @param  list<array<string, mixed>>|null  $formFields
     * @param  class-string<EventProvider>|null  $providerClass
     * @param  class-string<ActionHandler>|null  $handlerClass
     * @param  array<string, array{label:string, icon?:string, color?:string, handler?:Closure}>  $customActions
     */
    public function __construct(
        public ?string $sourceKey = null,
        public string|array|null $label = null,
        public string $color = 'blue',
        public string $startField = 'starts_at',
        public ?string $endField = 'ends_at',
        public int $defaultDurationMinutes = 60,
        public ?string $allDayField = 'all_day',
        public bool $defaultAllDay = false,
        public string|Closure $titleField = 'title',
        public ?string $descriptionField = 'description',
        public ?string $locationField = 'location',
        public ?string $statusField = 'status',
        public ?string $categoryField = 'category',
        public string|Closure|null $colorField = 'color',
        public string|Closure|null $urlField = null,
        public ?string $versionField = 'version',
        public ?string $movableField = 'movable',
        public ?string $timezoneField = 'timezone',
        public ?string $scopeField = null,
        public ?string $ownerField = null,
        public ?string $tenantField = null,
        public bool $publicRead = false,
        public ?Closure $readScope = null,
        public array $with = [],
        public array $searchableFields = ['title'],
        public array $statusOptions = [],
        public string|array $statusFilterLabel = ['fa' => 'وضعیت', 'en' => 'Status'],
        public array $categoryOptions = [],
        public string|array $categoryFilterLabel = ['fa' => 'دسته‌بندی', 'en' => 'Category'],
        public array $statusColors = [],
        public array $categoryColors = [],
        public array $filters = [],
        public array $filterCallbacks = [],
        public bool $readOnly = false,
        ?ActionCapabilities $capabilities = null,
        public ?Closure $capabilitiesResolver = null,
        public ?Closure $authorize = null,
        public ?array $formFields = null,
        public bool $supportsRecurrence = true,
        public ?Closure $writeMapper = null,
        public ?string $providerClass = null,
        public ?string $handlerClass = null,
        public array $customActions = [],
    ) {
        if ($this->sourceKey !== null && ! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $this->sourceKey)) {
            throw new InvalidArgumentException("Invalid model calendar source key [{$this->sourceKey}].");
        }
        if ($this->startField === '') {
            throw new InvalidArgumentException('Model calendar definition requires a non-empty startField.');
        }
        if ($this->defaultDurationMinutes < 1 || $this->defaultDurationMinutes > 525600) {
            throw new InvalidArgumentException('defaultDurationMinutes must be between 1 and 525600.');
        }
        if (! AllowedCssToken::isValidColor($this->color)) {
            throw new InvalidArgumentException("Invalid model calendar color [{$this->color}].");
        }
        if ($this->providerClass !== null && ! is_subclass_of($this->providerClass, EventProvider::class)) {
            throw new InvalidArgumentException("Custom providerClass [{$this->providerClass}] must implement EventProvider.");
        }
        if ($this->handlerClass !== null && ! is_subclass_of($this->handlerClass, ActionHandler::class)) {
            throw new InvalidArgumentException("Custom handlerClass [{$this->handlerClass}] must implement ActionHandler.");
        }

        $this->capabilities = $this->readOnly
            ? ActionCapabilities::none()
            : ($capabilities ?? ActionCapabilities::all());
    }

    public static function make(?string $sourceKey = null): self
    {
        return new self(sourceKey: $sourceKey);
    }

    public function withSourceKey(string $sourceKey): self
    {
        $clone = clone $this;
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $sourceKey)) {
            throw new InvalidArgumentException("Invalid model calendar source key [{$sourceKey}].");
        }
        $clone->sourceKey = $sourceKey;

        return $clone;
    }

    /** @param string|array<string, string> $label */
    public function label(string|array $label): self
    {
        $clone = clone $this;
        $clone->label = $label;

        return $clone;
    }

    public function color(string $color): self
    {
        if (! AllowedCssToken::isValidColor($color)) {
            throw new InvalidArgumentException("Invalid model calendar color [{$color}].");
        }
        $clone = clone $this;
        $clone->color = $color;

        return $clone;
    }

    public function timestamps(string $startField, ?string $endField = 'ends_at', int $defaultDurationMinutes = 60, ?string $allDayField = 'all_day'): self
    {
        if ($startField === '') {
            throw new InvalidArgumentException('startField cannot be empty.');
        }
        if ($defaultDurationMinutes < 1 || $defaultDurationMinutes > 525600) {
            throw new InvalidArgumentException('defaultDurationMinutes out of range.');
        }
        $clone = clone $this;
        $clone->startField = $startField;
        $clone->endField = $endField;
        $clone->defaultDurationMinutes = $defaultDurationMinutes;
        $clone->allDayField = $allDayField;

        return $clone;
    }

    public function scopeBy(?string $scopeField = 'scope_key', ?string $ownerField = null, ?string $tenantField = null): self
    {
        $clone = clone $this;
        $clone->scopeField = $scopeField;
        $clone->ownerField = $ownerField;
        $clone->tenantField = $tenantField;

        return $clone;
    }

    public function readScope(Closure $scope): self
    {
        $clone = clone $this;
        $clone->readScope = $scope;

        return $clone;
    }

    public function allowPublicRead(bool $public = true): self
    {
        $clone = clone $this;
        $clone->publicRead = $public;

        return $clone;
    }

    /** @param list<string> $relations */
    public function with(array $relations): self
    {
        $clone = clone $this;
        $clone->with = array_values($relations);

        return $clone;
    }

    /** @param list<string> $fields */
    public function searchable(array $fields): self
    {
        $clone = clone $this;
        $clone->searchableFields = array_values($fields);

        return $clone;
    }

    /**
     * @param  array<string, string>  $options
     * @param  array<string, string>  $colors
     * @param  string|array<string, string>|null  $filterLabel
     */
    public function statuses(array $options, array $colors = [], string|array|null $filterLabel = null): self
    {
        $clone = clone $this;
        $clone->statusOptions = $options;
        if ($colors !== []) {
            $clone->statusColors = $colors;
        }
        if ($filterLabel !== null) {
            $clone->statusFilterLabel = $filterLabel;
        }

        return $clone;
    }

    /**
     * @param  array<string, string>  $options
     * @param  array<string, string>  $colors
     * @param  string|array<string, string>|null  $filterLabel
     */
    public function categories(array $options, array $colors = [], string|array|null $filterLabel = null): self
    {
        $clone = clone $this;
        $clone->categoryOptions = $options;
        if ($colors !== []) {
            $clone->categoryColors = $colors;
        }
        if ($filterLabel !== null) {
            $clone->categoryFilterLabel = $filterLabel;
        }

        return $clone;
    }

    public function readOnly(bool $readOnly = true): self
    {
        $clone = clone $this;
        $clone->readOnly = $readOnly;
        if ($readOnly) {
            $clone->capabilities = ActionCapabilities::none();
        }

        return $clone;
    }

    public function capabilities(ActionCapabilities $capabilities, ?Closure $resolver = null): self
    {
        $clone = clone $this;
        $clone->capabilities = $this->readOnly ? ActionCapabilities::none() : $capabilities;
        if ($resolver !== null) {
            $clone->capabilitiesResolver = $resolver;
        }

        return $clone;
    }

    public function authorizeUsing(Closure $authorize): self
    {
        $clone = clone $this;
        $clone->authorize = $authorize;

        return $clone;
    }

    /** @param list<array<string, mixed>> $fields */
    public function formFields(array $fields): self
    {
        $clone = clone $this;
        $clone->formFields = $fields;

        return $clone;
    }

    public function writeUsing(Closure $mapper): self
    {
        $clone = clone $this;
        $clone->writeMapper = $mapper;

        return $clone;
    }

    public function hasScopeRule(): bool
    {
        return $this->publicRead
            || $this->readScope !== null
            || $this->scopeField !== null
            || $this->ownerField !== null
            || $this->tenantField !== null;
    }

    public function titleFieldName(): string
    {
        return is_string($this->titleField) && $this->titleField !== '' ? $this->titleField : 'title';
    }

    public function resolveLabel(?string $locale = null, string $fallback = ''): string
    {
        if (is_string($this->label) && $this->label !== '') {
            return $this->label;
        }
        if (is_array($this->label) && $this->label !== []) {
            $loc = $locale !== null ? strtolower(substr($locale, 0, 2)) : strtolower(substr((string) app()->getLocale(), 0, 2));
            if (isset($this->label[$loc]) && is_string($this->label[$loc]) && $this->label[$loc] !== '') {
                return $this->label[$loc];
            }
            if (isset($this->label['en']) && is_string($this->label['en']) && $this->label['en'] !== '') {
                return $this->label['en'];
            }
            $first = reset($this->label);
            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        return $fallback !== '' ? $fallback : ($this->sourceKey ?? 'events');
    }

    /** @param string|array<string, string> $raw */
    private function resolveText(string|array $raw, ?string $locale, string $default): string
    {
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }
        if (is_array($raw) && $raw !== []) {
            $loc = $locale !== null ? strtolower(substr($locale, 0, 2)) : strtolower(substr((string) app()->getLocale(), 0, 2));

            return (string) ($raw[$loc] ?? $raw['en'] ?? reset($raw) ?: $default);
        }

        return $default;
    }

    /**
     * Build declarative CalendarFilterData entries for status/category options + custom filters.
     *
     * @return list<CalendarFilterData>
     */
    public function buildFilters(string $sourceKey, ?string $locale = null): array
    {
        $out = [];
        $seen = [];

        if ($this->statusField !== null && $this->statusOptions !== []) {
            $f = new CalendarFilterData(
                $sourceKey,
                $this->statusField,
                $this->resolveText($this->statusFilterLabel, $locale, 'Status'),
                $this->statusOptions,
            );
            $out[] = $f;
            $seen[$f->key()] = true;
        }

        if ($this->categoryField !== null && $this->categoryOptions !== []) {
            $f = new CalendarFilterData(
                $sourceKey,
                $this->categoryField,
                $this->resolveText($this->categoryFilterLabel, $locale, 'Category'),
                $this->categoryOptions,
            );
            if (! isset($seen[$f->key()])) {
                $out[] = $f;
                $seen[$f->key()] = true;
            }
        }

        foreach ($this->filters as $filter) {
            if ($filter instanceof CalendarFilterData) {
                $rebound = $filter->sourceKey === $sourceKey
                    ? $filter
                    : new CalendarFilterData($sourceKey, $filter->field, $filter->label, $filter->options, $filter->default);
                if (! isset($seen[$rebound->key()])) {
                    $out[] = $rebound;
                    $seen[$rebound->key()] = true;
                }
            }
        }

        return $out;
    }

    public function defaultStatus(): ?string
    {
        if ($this->statusOptions === []) {
            return null;
        }
        $keys = array_keys($this->statusOptions);

        return isset($keys[0]) ? (string) $keys[0] : null;
    }

    public function defaultCategory(): ?string
    {
        if ($this->categoryOptions === []) {
            return null;
        }
        $keys = array_keys($this->categoryOptions);

        return isset($keys[0]) ? (string) $keys[0] : null;
    }
}
