<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario10WeekDayNavigationTest extends ScenarioCase
{
    public function test_week_view_steps_seven_days(): void
    {
        $this->personal(['view' => 'week', 'system' => 'gregorian', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('next')->assertSet('month', '7')->assertSet('day', '22')
            ->call('previous')->call('previous')->assertSet('day', '8');
    }

    public function test_day_view_steps_one_day_and_crosses_month_end(): void
    {
        $this->personal(['view' => 'day', 'system' => 'gregorian', 'year' => '2026', 'month' => '7', 'day' => '31'])
            ->call('next')->assertSet('month', '8')->assertSet('day', '1')
            ->call('previous')->assertSet('month', '7')->assertSet('day', '31');
    }

    public function test_week_view_in_jalali_steps_across_month_boundary(): void
    {
        $this->personal(['view' => 'week', 'system' => 'jalali', 'year' => '1405', 'month' => '1', 'day' => '28'])
            ->call('next')->assertSet('month', '2')->assertSet('day', '4'); // 1405/01/28 + 7d, Farvardin has 31 days
    }
}
