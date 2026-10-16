<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\CalendarView;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;

/**
 * Per-placement options written by the HOST in its own Blade (never by the browser; the Livewire
 * property that carries them is #[Locked]). They can only NARROW what the calendar definition allows:
 * fewer views / systems / sources, forced filters, a locale, a compact layout.
 */
final readonly class EmbedOptions
{
    public const LAYOUTS = ['full', 'compact', 'mini-month', 'agenda'];

    public const LOCALES = ['en', 'fa'];

    /**
     * @param  list<CalendarView>|null  $views
     * @param  list<CalendarSystem>|null  $systems
     * @param  list<string>|null  $sources
     * @param  array<string, string>  $presetFilters
     */
    public function __construct(
        public string $layout = 'full',
        public ?array $views = null,
        public ?array $systems = null,
        public ?array $sources = null,
        public array $presetFilters = [],
        public ?string $locale = null,
        public ?CalendarView $defaultView = null,
        public ?CalendarSystem $system = null,
        public ?bool $movable = null,
        public ?int $agendaLimit = null,
    ) {}

    /**
     * Build from loosely typed Blade attributes. Unknown values are rejected (404 at mount) rather than ignored,
     * so a typo never silently widens what is shown.
     *
     * Note: `$sources = null` means all sources allowed by the CalendarDefinition;
     * `$sources = []` is preserved as an empty list (never widened to `null`) and fails closed on apply().
     *
     * @param  list<string>|null  $views
     * @param  list<string>|null  $systems
     * @param  list<string>|null  $sources
     * @param  array<string, string>  $presetFilters
     */
    public static function make(
        string $layout = 'full',
        ?array $views = null,
        ?array $systems = null,
        ?array $sources = null,
        array $presetFilters = [],
        ?string $locale = null,
        ?string $defaultView = null,
        ?string $system = null,
        ?bool $movable = null,
        ?int $agendaLimit = null,
    ): self {
        $bad = fn () => abort(404);
        if (! in_array($layout, self::LAYOUTS, true)) {
            $bad();
        }
        if ($locale !== null && ! in_array($locale, self::LOCALES, true)) {
            $bad();
        }
        $v = $views === null ? null : array_map(fn ($x) => CalendarView::tryFrom((string) $x) ?? $bad(), array_values($views));
        $s = $systems === null ? null : array_map(fn ($x) => CalendarSystem::tryFrom((string) $x) ?? $bad(), array_values($systems));
        $normalizedSources = null;
        if ($sources !== null) {
            $normalizedSources = [];
            $seen = [];
            foreach ($sources as $key) {
                if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $key)) {
                    $bad();
                }
                if (isset($seen[$key])) {
                    $bad();
                }
                $seen[$key] = true;
                $normalizedSources[] = $key;
            }
        }
        foreach ($presetFilters as $k => $val) {
            if (! is_string($k) || ! is_scalar($val)) {
                $bad();
            }
        }
        if ($agendaLimit !== null && ($agendaLimit < 1 || $agendaLimit > 100)) {
            $bad();
        }

        return new self(
            $layout,
            $v === [] ? null : $v,
            $s === [] ? null : $s,
            $normalizedSources,
            array_map('strval', $presetFilters),
            $locale,
            $defaultView === null || $defaultView === '' ? null : (CalendarView::tryFrom($defaultView) ?? $bad()),
            $system === null || $system === '' ? null : (CalendarSystem::tryFrom($system) ?? $bad()),
            $movable,
            $agendaLimit,
        );
    }

    /** @return array<string, mixed> scalar-only form stored in the locked Livewire property */
    public function toArray(): array
    {
        return [
            'layout' => $this->layout,
            'views' => $this->views === null ? null : array_map(fn (CalendarView $v) => $v->value, $this->views),
            'systems' => $this->systems === null ? null : array_map(fn (CalendarSystem $s) => $s->value, $this->systems),
            'sources' => $this->sources,
            'presetFilters' => $this->presetFilters,
            'locale' => $this->locale,
            'defaultView' => $this->defaultView?->value,
            'system' => $this->system?->value,
            'movable' => $this->movable,
            'agendaLimit' => $this->agendaLimit,
        ];
    }

    /** @param  array<string, mixed>  $a */
    public static function fromArray(array $a): self
    {
        return self::make(
            (string) ($a['layout'] ?? 'full'),
            $a['views'] ?? null,
            $a['systems'] ?? null,
            array_key_exists('sources', $a) ? $a['sources'] : null,
            (array) ($a['presetFilters'] ?? []),
            $a['locale'] ?? null,
            $a['defaultView'] ?? null,
            $a['system'] ?? null,
            isset($a['movable']) && is_bool($a['movable']) ? $a['movable'] : null,
            isset($a['agendaLimit']) && is_int($a['agendaLimit']) ? $a['agendaLimit'] : null,
        );
    }

    public function compact(): bool
    {
        return $this->layout === 'compact';
    }

    public function widget(): bool
    {
        return in_array($this->layout, ['compact', 'mini-month', 'agenda'], true);
    }

    /** Apply to a freshly resolved calendar. Narrowing only. */
    public function apply(ResolvedCalendar $resolved): ResolvedCalendar
    {
        $def = $resolved->definition;
        $ctx = $resolved->context;

        if ($this->locale !== null) {
            $ctx = $ctx->withLocale($this->locale);
            app()->setLocale($this->locale);
        }

        $sources = $def->sources;
        $sourceData = $def->sourceData;
        $filters = $def->filters;
        if ($this->sources !== null) {
            if ($this->sources === []) {
                throw new CalendarAccessDenied('Embed selects no available source.');
            }
            foreach ($this->sources as $requestedKey) {
                if (! $def->hasSource($requestedKey)) {
                    throw new CalendarAccessDenied("Embed requests unauthorized or unknown source [{$requestedKey}].");
                }
            }
            $keep = array_values(array_intersect($def->sourceKeys(), $this->sources));
            if ($keep === []) {
                throw new CalendarAccessDenied('Embed selects no available source.');
            }
            $sources = array_intersect_key($sources, array_flip($keep));
            $sourceData = array_intersect_key($sourceData, array_flip($keep));
            $filters = array_values(array_filter($filters, fn ($f) => isset($sources[$f->sourceKey])));
        }

        $views = $this->views;
        $defaultView = $this->defaultView;
        if ($this->layout === 'mini-month') {
            $views = [CalendarView::Month];
            $defaultView = CalendarView::Month;
        } elseif ($this->layout === 'agenda') {
            $views = [CalendarView::List];
            $defaultView = CalendarView::List;
        }
        $settings = $def->settings->restrict($views, $this->systems, $defaultView, $this->system, $this->movable);

        return new ResolvedCalendar(
            new CalendarDefinition($def->key, $sources, $def->handlerClass, $settings, $def->views, $sourceData, $filters),
            $ctx,
        );
    }
}
