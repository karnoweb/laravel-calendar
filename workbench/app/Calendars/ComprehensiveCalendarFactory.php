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

final class ComprehensiveCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        $locale = (string) session('demo_locale', $context->locale);
        $isFa = $locale === 'fa';
        $theme = (string) session('demo_theme', 'luxe');
        if (! in_array($theme, ['luxe', 'dark', 'light', 'auto'], true)) {
            $theme = 'luxe';
        }
        $rawFeatures = (array) session('demo_features', []);
        $features = [];
        foreach ($rawFeatures as $k => $v) {
            if (is_string($k) && array_key_exists($k, CalendarSettings::FEATURES)) {
                $features[$k] = (bool) $v;
            }
        }

        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->modelSources(['tasks', 'classes', 'meetings'])
            ->holidays(true)
            ->settings(new CalendarSettings(
                defaultView: CalendarView::Month,
                enabledViews: [CalendarView::Month, CalendarView::Week, CalendarView::Day, CalendarView::List],
                calendarSystem: $isFa ? CalendarSystem::Jalali : CalendarSystem::Gregorian,
                enabledSystems: [CalendarSystem::Jalali, CalendarSystem::Gregorian],
                firstWeekday: $isFa ? 6 : 1,
                features: $features,
                styles: ['theme' => $theme, 'direction' => $isFa ? 'rtl' : 'ltr', 'class' => ''],
            ))
            ->build();
    }
}
