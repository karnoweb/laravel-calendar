<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoRecurringSeries;

final class Scenario37RecurringGregorianTest extends ScenarioCase
{
    public function test_weekly_monday_wednesday_series_expands_in_the_visible_week(): void
    {
        $this->series('Standup', '2026-07-06 10:00:00', '2026-07-06 10:30:00', ['frequency' => 'WEEKLY', 'byWeekday' => [1, 3], 'count' => 6]);

        $c = $this->onDay('2026-07-15', 'week');
        $days = array_map(fn ($e) => $e->startAt(self::TZ)->format('Y-m-d'), $c->viewData('read')->events);
        sort($days);

        $this->assertSame(['2026-07-13', '2026-07-15'], $days);
    }

    public function test_count_limits_the_total_number_of_occurrences(): void
    {
        $this->series('Standup', '2026-07-06 10:00:00', '2026-07-06 10:30:00', ['frequency' => 'WEEKLY', 'byWeekday' => [1, 3], 'count' => 6]);

        // 6 occurrences: Jul 6, 8, 13, 15, 20, 22 -> nothing on Jul 27
        $this->assertCount(0, $this->onDay('2026-07-27', 'week')->viewData('read')->events);
    }
}
