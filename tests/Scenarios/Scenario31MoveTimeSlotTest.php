<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario31MoveTimeSlotTest extends ScenarioCase
{
    public function test_dropping_on_a_time_slot_sets_the_new_start_and_keeps_duration(): void
    {
        $t = $this->task('Slot', '2026-07-15 09:00:00', '2026-07-15 10:00:00');

        $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestMove', $this->ref($t->id), '2026-07-15', 780, 'op-slot-1', '1')
            ->call('confirmPending');

        $t->refresh();
        $this->assertSame('2026-07-15 13:00', $this->localTime($t->starts_at));
        $this->assertSame('2026-07-15 14:00', $this->localTime($t->ends_at));
    }
}
