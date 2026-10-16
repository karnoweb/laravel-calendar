<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire;

/**
 * Upcoming agenda widget (`<livewire:calendar::upcoming-agenda ... />`).
 * Uses the exact same source-isolation, range-overlap and authorization engine as `Calendar`,
 * locked to the `agenda` layout with a configurable item limit.
 */
final class UpcomingAgenda extends Calendar
{
    public function mount(
        string $calendarKey,
        bool $urlSync = false,
        string $urlPrefix = '',
        string $layout = 'agenda',
        ?array $views = null,
        ?array $systems = null,
        ?array $sources = null,
        array $presetFilters = [],
        ?string $locale = null,
        ?string $defaultView = null,
        ?string $system = null,
        ?bool $movable = null,
        ?int $agendaLimit = 8,
        ?int $limit = null,
    ): void {
        parent::mount(
            $calendarKey,
            $urlSync,
            $urlPrefix,
            'agenda',
            $views,
            $systems,
            $sources,
            $presetFilters,
            $locale,
            $defaultView,
            $system,
            $movable,
            $limit ?? $agendaLimit ?? 8,
        );
    }
}
