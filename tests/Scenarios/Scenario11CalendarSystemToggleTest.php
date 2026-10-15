<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario11CalendarSystemToggleTest extends ScenarioCase
{
    public function test_toggle_keeps_the_same_real_day(): void
    {
        // 2026-07-15 == 1405/04/24
        $c = $this->personal(['view' => 'month', 'system' => 'gregorian', 'year' => '2026', 'month' => '7', 'day' => '15']);

        $c->call('setSystem', 'jalali')->assertSet('system', 'jalali')
            ->assertSet('year', '1405')->assertSet('month', '4')->assertSet('day', '24');

        $c->call('setSystem', 'gregorian')
            ->assertSet('year', '2026')->assertSet('month', '7')->assertSet('day', '15');
    }

    public function test_events_stay_on_their_real_day_after_toggle(): void
    {
        $this->task('Fixed day', '2026-07-15 09:00:00', '2026-07-15 10:00:00');

        $this->personal(['view' => 'list', 'system' => 'gregorian', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->assertSee('Fixed day')
            ->call('setSystem', 'jalali')
            ->assertSee('Fixed day');
    }

    public function test_unknown_system_value_is_ignored(): void
    {
        $this->personal(['view' => 'month', 'system' => 'gregorian', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('setSystem', 'hebrew')->assertSet('system', 'gregorian');
    }

    public function test_toggle_can_be_disabled_by_definition(): void
    {
        $this->assertFalse((new \Karnoweb\Calendar\Data\CalendarSettings(features: ['systemToggle' => false]))->feature('systemToggle'));
    }
}
