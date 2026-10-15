<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire\Concerns;

use Karnoweb\Calendar\Enums\CalendarView;
use Karnoweb\Calendar\Support\ResolvedCalendar;

trait HasViewSwitcher
{
    public function setView(string $view): void
    {
        $target = CalendarView::tryFrom($view);
        if ($target === null) {
            return;
        }
        $this->guarded(function (ResolvedCalendar $r) use ($target): void {
            if (! $r->definition->settings->viewEnabled($target)) {
                return;
            }
            if (! $r->definition->settings->feature('viewSwitcher') && $target !== $r->definition->settings->defaultView) {
                return;
            }
            $this->applyState($this->navState($r)->with(view: $target));
            $this->resetPaging();
            $this->drawerDate = null;
        });
    }
}
