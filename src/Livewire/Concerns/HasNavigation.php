<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire\Concerns;

use Karnoweb\Calendar\Calendar\CalendarEngine;
use Karnoweb\Calendar\Data\CalendarNavigationState;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Security\CalendarInputNormalizer;
use Karnoweb\Calendar\Support\ResolvedCalendar;
use Karnoweb\Calendar\Support\UrlStateSerializer;

trait HasNavigation
{
    protected function navState(ResolvedCalendar $resolved): CalendarNavigationState
    {
        return app(UrlStateSerializer::class)->parse([
            'view' => $this->view,
            'system' => $this->system,
            'year' => $this->year,
            'month' => $this->month,
            'day' => $this->day,
        ], $resolved->definition->settings, $resolved->context->locale, $resolved->context->timezone);
    }

    /** Write the canonical state back to the (URL-bound) properties. */
    protected function applyState(CalendarNavigationState $state): void
    {
        $p = app(UrlStateSerializer::class)->toParams($state);
        $this->view = $p['view'];
        $this->system = $p['system'];
        $this->year = (string) $p['year'];
        $this->month = (string) $p['month'];
        $this->day = (string) $p['day'];
    }

    protected function resetPaging(): void
    {
        $this->pages = 1;
        $this->drawerPages = 1;
    }

    protected function navigate(callable $transform): void
    {
        $this->guarded(function (ResolvedCalendar $r) use ($transform): void {
            $engine = app(CalendarEngine::class);
            $this->applyState($transform($engine, $this->navState($r)));
            $this->resetPaging();
            $this->drawerDate = null;
            $this->outcomeState = '';
        });
    }

    public function previous(): void
    {
        $this->navigate(fn (CalendarEngine $e, CalendarNavigationState $s) => $e->previous($s));
    }

    public function next(): void
    {
        $this->navigate(fn (CalendarEngine $e, CalendarNavigationState $s) => $e->next($s));
    }

    public function today(): void
    {
        $this->navigate(fn (CalendarEngine $e, CalendarNavigationState $s) => $e->goToToday($s));
    }

    /** Jump to a year/month typed in the display system. */
    public function goTo(string $year, string $month): void
    {
        $y = (int) CalendarInputNormalizer::digits($year);
        $m = (int) CalendarInputNormalizer::digits($month);
        if ($y < 1 || $m < 1 || $m > 12) {
            return;
        }
        $this->navigate(fn (CalendarEngine $e, CalendarNavigationState $s) => $e->goTo($s, $y, $m));
    }

    public function setSystem(string $system): void
    {
        $target = CalendarSystem::tryFrom($system);
        if ($target === null) {
            return;
        }
        $this->guarded(function (ResolvedCalendar $r) use ($target): void {
            if (! $r->definition->settings->feature('systemToggle') || ! $r->definition->settings->systemEnabled($target)) {
                return;
            }
            $this->applyState(app(CalendarEngine::class)->switchSystem($this->navState($r), $target));
        });
    }

    /** Fired when the URL / a bound property changes (including browser back/forward). */
    public function updated(string $name): void
    {
        if (in_array($name, ['view', 'system', 'year', 'month', 'day', 'search', 'filters', 'disabledSources'], true)
            || str_starts_with($name, 'filters.') || str_starts_with($name, 'disabledSources.')) {
            $this->resetPaging();
            $this->drawerDate = null;
        }
    }
}
