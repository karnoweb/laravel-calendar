<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\CalendarView;

/** view + cursor date (Gregorian ISO, the single source of truth) + calendar system + timezone. */
final readonly class CalendarNavigationState
{
    public function __construct(
        public CalendarView $view,
        public string $date,
        public CalendarSystem $system,
        public string $timezone,
    ) {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Navigation date must be Y-m-d.');
        }
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Invalid timezone.');
        }
    }

    public function with(?CalendarView $view = null, ?string $date = null, ?CalendarSystem $system = null): self
    {
        return new self($view ?? $this->view, $date ?? $this->date, $system ?? $this->system, $this->timezone);
    }
}
