<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use Karnoweb\Calendar\Contracts\CalendarCache;

/**
 * Opt-in provider-page cache. Invalidation is a per-(calendar, scope) version token that is part of
 * every key; bumping it orphans all earlier entries (they expire by TTL). Host handlers or the
 * mutation coordinator call invalidateScope() AFTER the host transaction committed.
 */
final class CalendarReadCache implements CalendarCache
{
    public function __construct(private readonly Repository $store) {}

    public function enabled(): bool
    {
        return true;
    }

    public function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        return $this->store->remember($key, $ttlSeconds, $callback);
    }

    public function invalidateScope(string $calendarKey, string $scopeKey): void
    {
        $this->store->forever($this->versionKey($calendarKey, $scopeKey), Str::random(12));
    }

    public function scopeVersion(string $calendarKey, string $scopeKey): string
    {
        return (string) $this->store->get($this->versionKey($calendarKey, $scopeKey), '0');
    }

    private function versionKey(string $calendarKey, string $scopeKey): string
    {
        return 'kw-calendar:ver:'.hash('sha256', $calendarKey.'|'.$scopeKey);
    }
}
