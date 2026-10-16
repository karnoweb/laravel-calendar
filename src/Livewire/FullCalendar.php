<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire;

/**
 * Full-featured calendar Livewire component (`<livewire:calendar::full-calendar ... />`).
 * Shares the same runtime, source-isolation, range-overlap and authorization engine as `Calendar`.
 */
final class FullCalendar extends Calendar
{
    public function mount(
        string $calendarKey,
        bool $urlSync = true,
        string $urlPrefix = '',
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
    ): void {
        parent::mount($calendarKey, $urlSync, $urlPrefix, 'full', $views, $systems, $sources, $presetFilters, $locale, $defaultView, $system, $movable, $agendaLimit);
    }
}
