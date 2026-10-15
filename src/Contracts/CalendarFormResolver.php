<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\CalendarFormResult;
use Karnoweb\Calendar\Data\CalendarFormSchema;
use Karnoweb\Calendar\Enums\CalendarSystem;

interface CalendarFormResolver
{
    public function schema(CalendarDefinition $definition, CalendarContext $context, string $sourceKey, ?CalendarEventData $existing = null): CalendarFormSchema;

    /** @param array<string, mixed> $input raw user input */
    public function validate(CalendarFormSchema $schema, array $input, CalendarContext $context, CalendarSystem $system): CalendarFormResult;
}
