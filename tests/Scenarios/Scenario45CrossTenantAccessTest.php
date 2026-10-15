<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario45CrossTenantAccessTest extends ScenarioCase
{
    public function test_other_tenant_cannot_see_or_touch_operations_data(): void
    {
        $s = $this->ops('shift', 'Tenant A shift', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_B);

        $this->open('operations', ['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->assertDontSee('Tenant A shift')
            ->call('requestDelete', $this->ref($s->id, 'shifts'), '1')->assertSet('pending', null)
            ->call('requestMove', $this->ref($s->id, 'shifts'), '2026-07-20', null, 'op-t-00001', '1')->assertSet('pending', null);

        $this->assertTrue(DemoTask::query()->whereKey($s->id)->exists());
        $this->assertSame('2026-07-15 09:00', $this->localTime($s->fresh()->starts_at));
    }

    public function test_tenant_cannot_be_chosen_from_the_browser(): void
    {
        $this->ops('shift', 'Tenant A shift', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_B);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        $this->open('operations')->set('calendarKey', 'public');
    }
}
