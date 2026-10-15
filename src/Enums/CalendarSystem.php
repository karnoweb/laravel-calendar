<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Enums;

enum CalendarSystem: string
{
    case Gregorian = 'gregorian';
    case Jalali = 'jalali';
}
