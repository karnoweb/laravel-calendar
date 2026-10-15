<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario03TwoCalendarIsolationTest extends ScenarioCase
{
    public function test_two_instances_do_not_mix_events_sources_or_state(): void
    {
        $this->task('Personal item', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->ops('class', 'Ops class', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_A, self::TZ);
        $props = ['view' => 'list', 'year' => '2026', 'month' => '7', 'day' => '15'];

        $personal = $this->open('personal', $props);
        $ops = $this->open('operations', $props);

        $personal->assertSee('Personal item')->assertDontSee('Ops class');
        $ops->assertSee('Ops class')->assertDontSee('Personal item');

        $ops->call('setView', 'month')->assertSet('view', 'month');
        $personal->assertSet('view', 'list');
    }

    public function test_instances_keep_independent_disabled_sources(): void
    {
        $this->ops('class', 'Math', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_A);
        $props = ['view' => 'list', 'year' => '2026', 'month' => '7', 'day' => '15'];

        $a = $this->open('operations', $props)->call('toggleSource', 'classes');
        $b = $this->open('operations', $props);

        $a->assertDontSee('Math');
        $b->assertSee('Math');
    }
}
