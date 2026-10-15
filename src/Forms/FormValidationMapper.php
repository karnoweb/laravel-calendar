<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Forms;

use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;

/** Maps domain exceptions to translation keys for the form. */
final class FormValidationMapper
{
    public static function dateError(InvalidCalendarDate $e): string
    {
        return match ($e->reason) {
            InvalidCalendarDate::GAP => 'calendar::calendar.validation.dst_gap',
            InvalidCalendarDate::AMBIGUOUS => 'calendar::calendar.validation.dst_ambiguous',
            default => 'calendar::calendar.validation.date_invalid',
        };
    }
}
