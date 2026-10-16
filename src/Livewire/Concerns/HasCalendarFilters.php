<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire\Concerns;

use Karnoweb\Calendar\Support\CalendarFilterEngine;
use Karnoweb\Calendar\Support\CalendarSearch;
use Karnoweb\Calendar\Support\FilterValidator;
use Karnoweb\Calendar\Support\ResolvedCalendar;

trait HasCalendarFilters
{
    public function toggleSource(string $sourceKey): void
    {
        $this->guarded(function (ResolvedCalendar $r) use ($sourceKey): void {
            if (! $r->definition->settings->feature('filters')) {
                return;
            }
            $this->disabledSources = app(CalendarFilterEngine::class)->toggle($r->definition, $this->disabledSources, $sourceKey);
            $this->resetPaging();
        });
    }

    public function setFilter(string $key, string $value): void
    {
        $this->guarded(function (ResolvedCalendar $r) use ($key, $value): void {
            if (! $r->definition->settings->feature('filters')) {
                return;
            }
            $next = $this->filters;
            if ($value === '') {
                unset($next[$key]);
                if (! str_contains($key, '.')) {
                    foreach (array_keys($next) as $existingKey) {
                        if (str_ends_with($existingKey, '.'.$key)) {
                            unset($next[$existingKey]);
                        }
                    }
                }
            } else {
                if (! str_contains($key, '.')) {
                    foreach (array_keys($next) as $existingKey) {
                        if (str_ends_with($existingKey, '.'.$key)) {
                            unset($next[$existingKey]);
                        }
                    }
                }
                $next[$key] = $value;
            }
            // Only values declared by the server survive.
            $this->filters = FilterValidator::sanitize($r->definition, $next);
            $this->resetPaging();
        });
    }

    public function resetFilters(): void
    {
        $this->filters = [];
        $this->disabledSources = [];
        $this->search = '';
        $this->resetPaging();
    }

    public function updatedSearch(): void
    {
        $this->search = CalendarSearch::normalize($this->search);
        $this->resetPaging();
    }
}
