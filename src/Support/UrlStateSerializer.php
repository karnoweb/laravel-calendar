<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Calendar\CalendarSystemRegistry;
use Karnoweb\Calendar\Calendar\IsoDate;
use Karnoweb\Calendar\Data\CalendarNavigationState;
use Karnoweb\Calendar\Data\CalendarSettings;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\CalendarView;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;
use Throwable;

/**
 * URL/query <-> navigation state. Year/month/day are expressed in the DISPLAY system recorded in
 * `system` (so 1405/7 with system=jalali is Mehr 1405). The URL never carries actor, tenant,
 * scope or timezone; malformed values fall back to safe defaults instead of failing.
 */
final class UrlStateSerializer
{
    public function __construct(private readonly CalendarSystemRegistry $systems) {}

    /** @param array<string, mixed> $params */
    public function parse(array $params, CalendarSettings $settings, string $locale, string $timezone): CalendarNavigationState
    {
        $view = CalendarView::tryFrom((string) ($params['view'] ?? '')) ?? $settings->defaultView;
        $system = CalendarSystem::tryFrom((string) ($params['system'] ?? ''))
            ?? $settings->calendarSystem
            ?? $this->systems->forLocale($locale);
        if (! $settings->feature('systemToggle') && $settings->calendarSystem !== null) {
            $system = $settings->calendarSystem;
        }
        if (! $settings->viewEnabled($view)) {
            $view = $settings->defaultView;
        }
        if (! $settings->systemEnabled($system)) {
            $system = $settings->calendarSystem ?? $settings->enabledSystems[0];
        }
        $impl = $this->systems->get($system);
        $today = (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('Y-m-d');

        $year = $this->int($params['year'] ?? null);
        $month = $this->int($params['month'] ?? null);
        $day = $this->int($params['day'] ?? null);

        $iso = $today;
        if ($year !== null || $month !== null) {
            [$ty, $tm, $td] = $impl->fromIso($today);
            $y = max(1, min($year ?? $ty, $system === CalendarSystem::Jalali ? 2999 : 9998));
            $m = max(1, min($month ?? $tm, 12));
            $d = $day ?? (($y === $ty && $m === $tm) ? $td : 1);
            $d = max(1, min($d, $impl->daysInMonth($y, $m)));
            try {
                $iso = $impl->toIso($y, $m, $d);
            } catch (InvalidCalendarDate) {
                $iso = $today;
            }
        } elseif ($day !== null) {
            $iso = $today;
        }

        return new CalendarNavigationState($view, $iso, $system, $timezone);
    }

    /** @return array{view:string, system:string, year:int, month:int, day:int} */
    public function toParams(CalendarNavigationState $state): array
    {
        [$y, $m, $d] = $this->systems->get($state->system)->fromIso($state->date);

        return ['view' => $state->view->value, 'system' => $state->system->value, 'year' => $y, 'month' => $m, 'day' => $d];
    }

    private function int(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $s = \Karnoweb\Calendar\Security\CalendarInputNormalizer::digits((string) $value);
        } catch (Throwable) {
            return null;
        }

        return preg_match('/^\d{1,4}$/', $s) ? (int) $s : null;
    }

    public static function isIso(string $value): bool
    {
        return IsoDate::isValid($value);
    }
}
