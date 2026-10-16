<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Cache;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\EventQuery;

/**
 * Every variable that can change the read result is hashed into the cache key so two users / tenants /
 * timezones / source selections / filter sets never hit the same entry.
 */
final class CalendarCacheKey
{
    /** @param list<string> $selectedSources */
    public static function for(
        CalendarContext $context,
        string $sourceKey,
        EventQuery $query,
        string $scopeVersion,
        array $selectedSources = [],
    ): string {
        $filters = $query->filters;
        ksort($filters);
        $sortedSources = array_values($selectedSources);
        sort($sortedSources);

        $parts = [
            'cal' => $context->calendarKey,
            'scope' => $context->scopeKey,
            'actor' => $context->actorId ?? '~anonymous',
            'tz' => $context->timezone,
            'src' => $sourceKey,
            'sel' => $sortedSources,
            'range' => $query->range->signature(),
            'filters' => $filters,
            'search' => $query->search,
            'limit' => $query->limit,
            'cursor' => $query->cursor,
            'ver' => $scopeVersion,
        ];

        return 'karnoweb.calendar.read.v1.'.hash('sha256', (string) json_encode($parts, JSON_THROW_ON_ERROR));
    }
}
