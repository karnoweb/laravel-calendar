<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Providers;

use Illuminate\Support\Facades\Log;
use Karnoweb\Calendar\Contracts\CalendarObserver;

/** Optional observer writing scalar-only observations to the application log. */
final class LogCalendarObserver implements CalendarObserver
{
    public function observe(string $name, array $data): void
    {
        Log::channel(config('calendar.observability.log_channel'))->info('calendar.'.$name, $data);
    }
}
