<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use Karnoweb\Calendar\Enums\CalendarSystem;

final class CalendarSystemRegistry
{
    /** @var array<string, CalendarSystemContract> */
    private array $systems;

    public function __construct()
    {
        $this->systems = [
            CalendarSystem::Gregorian->value => new GregorianCalendarSystem,
            CalendarSystem::Jalali->value => new JalaliCalendarSystem,
        ];
    }

    public function get(CalendarSystem $system): CalendarSystemContract
    {
        return $this->systems[$system->value];
    }

    /** fa => Jalali, everything else => Gregorian. */
    public function forLocale(string $locale): CalendarSystem
    {
        return str_starts_with(strtolower($locale), 'fa') ? CalendarSystem::Jalali : CalendarSystem::Gregorian;
    }
}
