<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario13MonthThreeMoreTest extends ScenarioCase
{
    public function test_month_cell_shows_three_events_then_more_and_the_drawer_shows_all(): void
    {
        foreach (range(1, 5) as $i) {
            $this->task("Ev{$i}", sprintf('2026-07-15 %02d:00:00', 5 + $i), sprintf('2026-07-15 %02d:30:00', 5 + $i));
        }

        $c = $this->onDay('2026-07-15', 'month');
        $c->assertSee('Ev1')->assertSee('Ev2')->assertSee('Ev3')->assertDontSee('Ev4')->assertDontSee('Ev5')->assertSee('+2 more');

        $c->call('openDay', '2026-07-15')->assertSee('Ev4')->assertSee('Ev5')->assertSee('Events on');
        $c->call('closeDay')->assertSet('drawerDate', null);
    }

    public function test_no_more_link_when_events_fit(): void
    {
        $this->task('Only', '2026-07-15 09:00:00', '2026-07-15 10:00:00');

        $this->onDay('2026-07-15', 'month')->assertSee('Only')->assertDontSee('more');
    }
}
