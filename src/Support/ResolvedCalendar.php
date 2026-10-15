<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;

/** A definition and the context it was built for, valid for ONE request only. */
final readonly class ResolvedCalendar
{
    public function __construct(
        public CalendarDefinition $definition,
        public CalendarContext $context,
    ) {}
}
