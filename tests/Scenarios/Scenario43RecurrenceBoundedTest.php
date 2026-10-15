<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoRecurringSeries;

final class Scenario43RecurrenceBoundedTest extends ScenarioCase
{
    public function test_unbounded_daily_series_is_expanded_only_inside_the_visible_range(): void
    {
        $this->series('Forever', '2026-07-01 10:00:00', '2026-07-01 10:30:00', ['frequency' => 'DAILY', 'count' => null]);

        $c = $this->onDay('2026-07-15', 'month');
        $this->assertLessThanOrEqual(42, count($c->viewData('read')->events));
        $this->assertGreaterThan(20, count($c->viewData('read')->events));
    }

    public function test_far_future_view_is_still_bounded(): void
    {
        $this->series('Forever', '2026-07-01 10:00:00', '2026-07-01 10:30:00', ['frequency' => 'DAILY', 'count' => null]);

        $started = microtime(true);
        $c = $this->onDay('2090-01-15', 'month');

        $this->assertLessThan(5.0, microtime(true) - $started);
        $this->assertLessThanOrEqual(42, count($c->viewData('read')->events));
    }
}
