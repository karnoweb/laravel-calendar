<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario29DeleteEventTest extends ScenarioCase
{
    public function test_delete_requires_confirmation_then_removes_the_event(): void
    {
        $t = $this->task('Doomed', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $c = $this->personal(['view' => 'day', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestDelete', $this->ref($t->id), '1');

        $this->assertTrue(DemoTask::query()->whereKey($t->id)->exists(), 'nothing is deleted before confirmation');
        $c->call('confirmPending')->assertDontSee('Doomed');
        $this->assertFalse(DemoTask::query()->whereKey($t->id)->exists());
    }
}
