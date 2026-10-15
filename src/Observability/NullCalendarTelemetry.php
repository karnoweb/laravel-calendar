<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Observability;

use Closure;
use Karnoweb\Calendar\Providers\NullCalendarObserver;

/** Default: zero overhead, no observation. */
final class NullCalendarTelemetry extends CalendarTelemetry
{
    public function __construct()
    {
        parent::__construct(new NullCalendarObserver);
    }

    public function measure(string $name, Closure $callback, array $tags = []): mixed
    {
        return $callback();
    }

    public function record(string $name, array $data = []): void {}
}
