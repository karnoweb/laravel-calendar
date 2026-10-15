<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario30MoveDateKeepsClockTest extends ScenarioCase
{
    public function test_moving_to_another_day_keeps_clock_time_and_duration(): void
    {
        $t = $this->task('Move me', '2026-07-15 10:00:00', '2026-07-15 11:30:00');

        $this->personal(['view' => 'month', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestMove', $this->ref($t->id), '2026-07-20', null, 'op-move-1', '1')
            ->call('confirmPending');

        $t->refresh();
        $this->assertSame('2026-07-20 10:00', $this->localTime($t->starts_at));
        $this->assertSame('2026-07-20 11:30', $this->localTime($t->ends_at));
    }
}
