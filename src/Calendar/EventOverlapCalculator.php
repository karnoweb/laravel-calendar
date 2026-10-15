<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Calendar;

use DateTimeInterface;

/** Half-open interval overlap: a.start < b.end && a.end > b.start. Touching intervals do not overlap. */
final class EventOverlapCalculator
{
    public static function overlaps(DateTimeInterface $aStart, DateTimeInterface $aEnd, DateTimeInterface $bStart, DateTimeInterface $bEnd): bool
    {
        return $aStart < $bEnd && $aEnd > $bStart;
    }
}
