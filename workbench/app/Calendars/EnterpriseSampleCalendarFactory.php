<?php

declare(strict_types=1);

namespace Workbench\App\Calendars;

use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\CalendarView;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDefinition;
use Karnoweb\Calendar\Support\CalendarBuilder;

/** Sample host calendars. Each key loads only its declared sources. */
final class EnterpriseSampleCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        $locale = (string) session('demo_locale', $context->locale);
        $isFa = $locale === 'fa';
        $builder = (new CalendarBuilder)->key($context->calendarKey);

        match ($context->calendarKey) {
            'my.calendar' => $builder->modelSources(['tasks']),
            'education.calendar' => $builder->modelSources(['classes']),
            'crm.calendar' => $builder->modelSources(['opportunities', 'meetings']),
            'delivery.calendar' => $builder->modelSources(['orders']),
            'overview.calendar' => $builder->modelSources(['tasks', 'orders', 'tickets', 'opportunities', 'classes', 'meetings'])->holidays(true),
            default => throw new InvalidCalendarDefinition('Unknown enterprise sample calendar.'),
        };

        return $builder->settings(new CalendarSettings(
            defaultView: CalendarView::Month,
            enabledViews: [CalendarView::Month, CalendarView::Week, CalendarView::Day, CalendarView::List],
            calendarSystem: $isFa ? CalendarSystem::Jalali : CalendarSystem::Gregorian,
            enabledSystems: [CalendarSystem::Jalali, CalendarSystem::Gregorian],
            firstWeekday: $isFa ? 6 : 1,
            styles: ['theme' => 'luxe', 'direction' => $isFa ? 'rtl' : 'ltr', 'class' => ''],
        ))->build();
    }
}
