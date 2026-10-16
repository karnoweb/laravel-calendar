<?php

declare(strict_types=1);

namespace Workbench\App\Calendars;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\CalendarView;
use Karnoweb\Calendar\Support\CalendarBuilder;

final class ClassesOnlyCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        $locale = (string) session('demo_locale', $context->locale);
        $isFa = $locale === 'fa';
        $theme = (string) session('demo_theme', 'luxe');
        if (! in_array($theme, ['luxe', 'dark', 'light', 'auto'], true)) {
            $theme = 'luxe';
        }

        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->modelSources(['classes'])
            ->settings(new CalendarSettings(
                defaultView: CalendarView::Month,
                enabledViews: [CalendarView::Month, CalendarView::Week, CalendarView::Day, CalendarView::List],
                calendarSystem: $isFa ? CalendarSystem::Jalali : CalendarSystem::Gregorian,
                enabledSystems: [CalendarSystem::Jalali, CalendarSystem::Gregorian],
                firstWeekday: $isFa ? 6 : 1,
                styles: ['theme' => $theme, 'direction' => $isFa ? 'rtl' : 'ltr', 'class' => ''],
            ))
            ->build();
    }
}
