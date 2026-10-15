<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Closure;

interface CalendarCache
{
    public function enabled(): bool;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $key, int $ttlSeconds, Closure $callback): mixed;

    /** Invalidate every cached read for a (calendar, scope). Called after a successful mutation. */
    public function invalidateScope(string $calendarKey, string $scopeKey): void;

    /** Opaque token that changes whenever invalidateScope() runs. */
    public function scopeVersion(string $calendarKey, string $scopeKey): string;
}
