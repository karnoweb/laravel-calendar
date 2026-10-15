<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario05JalaliNowruzTest extends ScenarioCase
{
    public function test_last_day_of_esfand_rolls_into_farvardin_in_state_and_grid(): void
    {
        // 1404/12/29 = 2026-03-20 (last day of 1404), 1405/01/01 = 2026-03-21 (Nowruz)
        $this->task('Eid prep', '2026-03-20 10:00:00', '2026-03-20 11:00:00');
        $this->task('Nowruz party', '2026-03-21 10:00:00', '2026-03-21 11:00:00');

        $c = $this->personal(['view' => 'month', 'system' => 'jalali', 'year' => '1404', 'month' => '12', 'day' => '29']);
        $c->assertSee('Eid prep')->assertDontSee('Nowruz party');

        $c->call('next')->assertSet('year', '1405')->assertSet('month', '1')->assertSee('Farvardin')->assertSee('Nowruz party');
    }

    public function test_previous_from_farvardin_returns_to_esfand_of_previous_year(): void
    {
        $this->personal(['view' => 'month', 'system' => 'jalali', 'year' => '1405', 'month' => '1', 'day' => '1'])
            ->call('previous')
            ->assertSet('year', '1404')
            ->assertSet('month', '12')
            ->assertSee('Esfand');
    }
}
