<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\CalendarContext;

/** Optional persistence of view/filter preferences. Never influences authorization. */
interface PreferenceStore
{
    /** @return array<string, mixed> */
    public function get(CalendarContext $context): array;

    /** @param array<string, mixed> $preferences */
    public function put(CalendarContext $context, array $preferences): void;
}
