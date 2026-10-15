<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Providers;

use Karnoweb\Calendar\Contracts\CalendarObserver;

final class NullCalendarObserver implements CalendarObserver
{
    public function observe(string $name, array $data): void {}
}
