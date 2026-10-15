<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Enums;

enum RecurrenceScope: string
{
    case This = 'this';
    case Future = 'future';
    case All = 'all';

    public function updateAction(): CalendarAction
    {
        return match ($this) {
            self::This => CalendarAction::OccurrenceUpdate,
            self::Future => CalendarAction::SeriesSplit,
            self::All => CalendarAction::SeriesUpdate,
        };
    }

    public function deleteAction(): CalendarAction
    {
        return match ($this) {
            self::This => CalendarAction::OccurrenceDelete,
            self::Future => CalendarAction::SeriesSplit,
            self::All => CalendarAction::SeriesDelete,
        };
    }
}
