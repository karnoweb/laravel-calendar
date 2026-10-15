<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Enums;

enum RecurrenceFrequency: string
{
    case Daily = 'DAILY';
    case Weekly = 'WEEKLY';
    case Monthly = 'MONTHLY';
    case Yearly = 'YEARLY';
}
