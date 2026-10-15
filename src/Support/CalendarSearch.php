<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Security\SafeCalendarMarkup;

/** Search text rules: bounded length, no control chars. Matching itself is delegated to providers. */
final class CalendarSearch
{
    public static function normalize(?string $term, int $maxLength = 120): string
    {
        return SafeCalendarMarkup::text($term ?? '', $maxLength);
    }

    /** Helper for SQL providers: escapes LIKE wildcards in a normalised term. */
    public static function escapeLike(string $term, string $escape = '\\'): string
    {
        return str_replace([$escape, '%', '_'], [$escape.$escape, $escape.'%', $escape.'_'], $term);
    }
}
