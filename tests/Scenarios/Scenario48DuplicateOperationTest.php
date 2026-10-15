<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario48DuplicateOperationTest extends ScenarioCase
{
    public function test_same_operation_id_replays_once_and_never_applies_twice(): void
    {
        $t = $this->task('Once', '2026-07-15 10:00:00', '2026-07-15 11:00:00');
        $c = $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15']);

        $c->call('requestMove', $this->ref($t->id), '2026-07-20', null, 'op-dup-00001', '1')->call('confirmPending');
        $c->call('requestMove', $this->ref($t->id), '2026-07-20', null, 'op-dup-00001', '1')->call('confirmPending');

        $t->refresh();
        $this->assertSame(2, (int) $t->version, 'applied exactly once');
        $this->assertSame('2026-07-20 10:00', $this->localTime($t->starts_at));
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('demo_action_journal')->count());
    }
}
