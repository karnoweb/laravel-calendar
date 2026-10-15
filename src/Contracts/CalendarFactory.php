<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;

interface CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition;
}
