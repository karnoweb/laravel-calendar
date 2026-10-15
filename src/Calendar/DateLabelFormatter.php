<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Enums\CalendarSystem;

/** Month/day names and digit shaping for the active locale. Names come from the translation files. */
final class DateLabelFormatter
{
    public function monthName(CalendarSystem $system, int $month, string $locale): string
    {
        $names = trans('calendar::calendar.months.'.$system->value, [], $locale);

        return is_array($names) ? (string) ($names[$month] ?? $month) : (string) $month;
    }

    /** @param int $weekday 0 = Sunday ... 6 = Saturday */
    public function weekdayName(int $weekday, string $locale, bool $short = false): string
    {
        $names = trans('calendar::calendar.weekdays.'.($short ? 'short' : 'long'), [], $locale);

        return is_array($names) ? (string) ($names[$weekday] ?? $weekday) : (string) $weekday;
    }

    public function digits(string|int $value, string $locale): string
    {
        $value = (string) $value;
        if (! str_starts_with(strtolower($locale), 'fa')) {
            return $value;
        }

        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    public function monthTitle(CalendarSystem $system, int $year, int $month, string $locale): string
    {
        return $this->monthName($system, $month, $locale).' '.$this->digits($year, $locale);
    }

    public function time(DateTimeImmutable $instant, string $timezone, string $locale): string
    {
        return $this->digits($instant->setTimezone(new DateTimeZone($timezone))->format('H:i'), $locale);
    }

    public function dayTitle(CalendarSystemContract $system, string $iso, string $locale): string
    {
        [$y, $m, $d] = $system->fromIso($iso);

        return $this->weekdayName(IsoDate::weekday($iso), $locale).' '.$this->digits($d, $locale).' '.$this->monthName($system->system(), $m, $locale).' '.$this->digits($y, $locale);
    }

    public function hourLabel(int $hour, string $locale): string
    {
        return $this->digits(sprintf('%02d:00', $hour), $locale);
    }
}
