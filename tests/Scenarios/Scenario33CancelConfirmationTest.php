<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario33CancelConfirmationTest extends ScenarioCase
{
    public function test_cancelled_confirmation_never_reaches_the_handler(): void
    {
        $t = $this->task('Safe', '2026-07-15 09:00:00', '2026-07-15 10:00:00');

        $this->personal(['view' => 'day', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestDelete', $this->ref($t->id), '1')
            ->call('cancelPending')->assertSet('pending', null)
            ->call('confirmPending'); // nothing staged: must be a no-op

        $this->assertTrue(DemoTask::query()->whereKey($t->id)->exists());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('demo_action_journal')->count());
    }
}
