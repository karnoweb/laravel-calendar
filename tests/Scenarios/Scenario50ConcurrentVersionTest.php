<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario50ConcurrentVersionTest extends ScenarioCase
{
    public function test_stale_version_is_a_conflict_and_changes_nothing(): void
    {
        $t = $this->task('Shared', '2026-07-15 10:00:00', '2026-07-15 11:00:00');
        $c = $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15']);

        // another user saves first
        $t->update(['title' => 'Edited elsewhere', 'version' => 2]);

        $c->call('requestMove', $this->ref($t->id), '2026-07-20', null, 'op-cc-000001', '1')->call('confirmPending')
            ->assertSet('outcomeState', 'conflict');

        $t->refresh();
        $this->assertSame('2026-07-15 10:00', $this->localTime($t->starts_at));
        $this->assertSame(2, (int) $t->version);
    }
}
