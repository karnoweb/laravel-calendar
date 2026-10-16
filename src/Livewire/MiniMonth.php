<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire;

/**
 * Compact month calendar widget (`<livewire:calendar::mini-month ... />`).
 * Uses the exact same source-isolation, range-overlap and authorization engine as `Calendar`,
 * locked to the `mini-month` layout.
 */
final class MiniMonth extends Calendar
{
    public function mount(
        string $calendarKey,
        bool $urlSync = false,
        string $urlPrefix = '',
        string $layout = 'mini-month',
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
        parent::mount($calendarKey, $urlSync, $urlPrefix, 'mini-month', $views, $systems, $sources, $presetFilters, $locale, $defaultView, $system, $movable, $agendaLimit);
    }
}
