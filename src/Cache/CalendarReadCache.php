<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use Karnoweb\Calendar\Contracts\CalendarCache;

/**
 * Optional read cache (disabled by default in config). Invalidation bumps a per-(calendar, scope) AND
 * per-scope version token so any driver works — no cache tags required — and cross-calendar views sharing
 * the same scope are invalidated together on mutation.
 */
final class CalendarReadCache implements CalendarCache
{
    public function __construct(private readonly CacheRepository $store) {}

    public function enabled(): bool
    {
        return (bool) config('calendar.cache.enabled', false);
    }

    public function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }
        $ttl = max(1, $ttlSeconds);
        $cached = $this->store->get($key);
        if ($cached !== null) {
            return $cached;
        }
        $fresh = $callback();
        $this->store->put($key, $fresh, $ttl);

        return $fresh;
    }

    public function scopeVersion(string $calendarKey, string $scopeKey): string
    {
        $calVer = (string) ($this->store->get($this->versionKey($calendarKey, $scopeKey)) ?? '0');
        $globalVer = (string) ($this->store->get($this->globalScopeVersionKey($scopeKey)) ?? '0');

        return $calVer.':'.$globalVer;
    }

    public function invalidateScope(string $calendarKey, string $scopeKey): void
    {
        $token = Str::random(12);
        $this->store->forever($this->versionKey($calendarKey, $scopeKey), $token);
        $this->store->forever($this->globalScopeVersionKey($scopeKey), $token);
    }

    private function versionKey(string $calendarKey, string $scopeKey): string
    {
        return 'karnoweb.calendar.ver.v1.'.hash('sha256', $calendarKey.'|'.$scopeKey);
    }

    private function globalScopeVersionKey(string $scopeKey): string
    {
        return 'karnoweb.calendar.scope_ver.v1.'.hash('sha256', $scopeKey);
    }
}
