<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Cache;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\EventQuery;

/**
 * Cache keys include everything that can change the answer: calendar, scope, ACTOR, source,
 * range, filters, search, paging and the scope version token. Two actors never share a key.
 */
final class CalendarCacheKey
{
    public static function for(CalendarContext $context, string $sourceKey, EventQuery $query, string $scopeVersion): string
    {
        $parts = [
            'cal' => $context->calendarKey,
            'scope' => $context->scopeKey,
            'actor' => $context->actorId ?? '~anonymous',
            'tz' => $context->timezone,
            'src' => $sourceKey,
            'range' => $query->range->signature(),
            'filters' => $query->filters,
            'search' => $query->search,
            'limit' => $query->limit,
            'cursor' => $query->cursor,
            'ver' => $scopeVersion,
        ];
        ksort($parts);

        return 'kw-calendar:read:'.hash('sha256', (string) json_encode($parts));
    }
}
