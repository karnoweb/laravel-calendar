<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Cache;

use Closure;
use Karnoweb\Calendar\Contracts\CalendarCache;

/** Default: caching disabled, therefore no cross-actor leak is possible. */
final class NullCalendarCache implements CalendarCache
{
    public function enabled(): bool
    {
        return false;
    }

    public function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        return $callback();
    }

    public function invalidateScope(string $calendarKey, string $scopeKey): void {}

    public function scopeVersion(string $calendarKey, string $scopeKey): string
    {
        return '0';
    }
}
