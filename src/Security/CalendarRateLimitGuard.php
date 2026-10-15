<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Security;

use Illuminate\Cache\RateLimiter;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Exceptions\CalendarRateLimited;

/** Cost guards: request rate, payload size, search length, range size. */
final class CalendarRateLimitGuard
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function hit(CalendarContext $context, string $bucket): void
    {
        if (! config('calendar.rate_limit.enabled', true)) {
            return;
        }
        $max = (int) config("calendar.rate_limit.{$bucket}_per_minute", $bucket === 'write' ? 60 : 240);
        $key = 'calendar:'.$bucket.':'.hash('sha256', $context->calendarKey.'|'.$context->scopeKey.'|'.($context->actorId ?? 'anon'));

        if ($this->limiter->tooManyAttempts($key, $max)) {
            throw new CalendarRateLimited;
        }
        $this->limiter->hit($key, 60);
    }

    public function assertRange(CalendarDefinition $definition, DateRange $range): void
    {
        if ($range->days() > $definition->settings->limit('maxRangeDays')) {
            throw new CalendarRateLimited('Requested range is too large.');
        }
    }

    public function assertSearch(CalendarDefinition $definition, string $search): string
    {
        $search = SafeCalendarMarkup::text($search, $definition->settings->limit('maxSearchLength'));

        return $definition->settings->feature('search') ? $search : '';
    }

    /** @param array<string, mixed> $payload */
    public function assertPayload(CalendarDefinition $definition, array $payload): void
    {
        $size = strlen((string) json_encode($payload));
        if ($size > $definition->settings->limit('maxPayloadBytes')) {
            throw new CalendarRateLimited('Payload is too large.');
        }
    }
}
