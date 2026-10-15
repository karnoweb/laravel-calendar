<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoRecurringSeries;

final class Scenario41RecurringFutureSplitTest extends ScenarioCase
{
    public function test_future_scope_splits_the_series_without_losing_or_duplicating_days(): void
    {
        $s = $this->series('Daily', '2026-07-13 10:00:00', '2026-07-13 11:00:00', ['frequency' => 'DAILY', 'count' => 6]);

        $c = $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15']);
        $c->call('requestMove', $this->ref($s->id, 'tasks', '2026-07-15T10:00:00'), '2026-07-15', 900, 'op-split-1', '1')
            ->call('chooseScope', 'future')->call('confirmPending');

        $this->assertSame(2, DemoRecurringSeries::count());
        $first = $s->fresh();
        $second = DemoRecurringSeries::where('id', '!=', $s->id)->firstOrFail();
        $this->assertSame(2, $first->rule['count']);
        $this->assertSame(4, $second->rule['count']);

        $c = $this->personal(['view' => 'month', 'year' => '2026', 'month' => '7', 'day' => '15']);
        $days = array_map(fn ($e) => $e->startAt(self::TZ)->format('Y-m-d H:i'), $c->viewData('read')->events);
        sort($days);
        $this->assertSame(['2026-07-13 10:00', '2026-07-14 10:00', '2026-07-15 15:00', '2026-07-16 15:00', '2026-07-17 15:00', '2026-07-18 15:00'], $days);
    }
}
