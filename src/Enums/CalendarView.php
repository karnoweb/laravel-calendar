<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Enums;

enum CalendarView: string
{
    case Month = 'month';
    case Week = 'week';
    case Day = 'day';
    case List = 'list';
}
