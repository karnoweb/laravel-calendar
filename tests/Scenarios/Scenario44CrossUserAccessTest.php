<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario44CrossUserAccessTest extends ScenarioCase
{
    public function test_another_actor_cannot_see_or_touch_my_event(): void
    {
        $t = $this->task('Mine', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->loginAs(self::ACTOR_B, self::TENANT_A);

        $this->open('personal', ['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->assertDontSee('Mine')
            ->call('openEvent', $this->ref($t->id))->assertSet('detailsRef', null)
            ->call('openEdit', $this->ref($t->id))->assertSet('formOpen', false)
            ->call('requestMove', $this->ref($t->id), '2026-07-20', null, 'op-x-0001', '1')->assertSet('pending', null)
            ->call('requestDelete', $this->ref($t->id), '1')->assertSet('pending', null);

        $t->refresh();
        $this->assertSame('2026-07-15 09:00', $this->localTime($t->starts_at));
        $this->assertTrue(DemoTask::query()->whereKey($t->id)->exists());
    }
}
