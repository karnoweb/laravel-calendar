<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoRecurringSeries;

final class Scenario39OccurrenceOverrideTest extends ScenarioCase
{
    public function test_this_only_move_overrides_one_occurrence_and_keeps_its_identity(): void
    {
        $s = $this->series('Daily', '2026-07-13 10:00:00', '2026-07-13 11:00:00', ['frequency' => 'DAILY', 'count' => 5]);

        $c = $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15']);
        $c->call('requestMove', $this->ref($s->id, 'tasks', '2026-07-14T10:00:00'), '2026-07-14', 900, 'op-ov-1', '1')
            ->call('chooseScope', 'this')->call('confirmPending');

        $s->refresh();
        $this->assertCount(1, $s->exceptions);
        $this->assertNotSame('cancelled', $s->exceptions[0]['kind']);
        $this->assertSame(1, DemoRecurringSeries::count());

        $times = [];
        foreach ($c->viewData('read')->events as $e) {
            $times[$e->reference->occurrenceKey] = $e->startAt(self::TZ)->format('H:i');
        }
        $this->assertSame('15:00', $times['2026-07-14T10:00:00']);
        $this->assertSame('10:00', $times['2026-07-15T10:00:00']);
    }
}
