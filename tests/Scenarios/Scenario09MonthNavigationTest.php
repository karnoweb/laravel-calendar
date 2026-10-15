<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario09MonthNavigationTest extends ScenarioCase
{
    public function test_next_previous_cross_the_year_boundary(): void
    {
        $this->personal(['view' => 'month', 'system' => 'gregorian', 'year' => '2026', 'month' => '12', 'day' => '15'])
            ->call('next')->assertSet('year', '2027')->assertSet('month', '1')
            ->call('previous')->assertSet('year', '2026')->assertSet('month', '12');
    }

    public function test_month_end_day_is_clamped_when_stepping(): void
    {
        $this->personal(['view' => 'month', 'system' => 'gregorian', 'year' => '2026', 'month' => '1', 'day' => '31'])
            ->call('next')->assertSet('month', '2')->assertSet('day', '28');
    }

    public function test_go_to_picks_year_and_month_and_today_returns(): void
    {
        $today = now(self::TZ);
        $this->personal(['view' => 'month', 'system' => 'gregorian'])
            ->call('goTo', '2030', '5')->assertSet('year', '2030')->assertSet('month', '5')
            ->call('today')->assertSet('year', (string) $today->year)->assertSet('month', (string) $today->month);
    }

    public function test_go_to_rejects_garbage_without_error(): void
    {
        $this->personal(['view' => 'month', 'system' => 'gregorian', 'year' => '2026', 'month' => '7', 'day' => '1'])
            ->call('goTo', 'abc', '99')->assertSet('year', '2026')->assertSet('month', '7');
    }
}
