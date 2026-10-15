<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario06JalaliLeapYearTest extends ScenarioCase
{
    public function test_leap_year_1403_has_esfand_30(): void
    {
        $this->personal(['view' => 'month', 'system' => 'jalali', 'year' => '1403', 'month' => '12', 'day' => '30'])
            ->assertSet('day', '30'); // 1403/12/30 = 2025-03-20 is a real date
    }

    public function test_common_year_1404_clamps_esfand_30_to_29(): void
    {
        $c = $this->personal(['view' => 'month', 'system' => 'jalali', 'year' => '1404', 'month' => '12', 'day' => '30']);
        $c->call('today')->call('goTo', '1404', '12');

        $c->assertSet('year', '1404')->assertSet('month', '12');
        $this->assertLessThanOrEqual(29, (int) $c->get('day'), 'no imaginary 1404/12/30');
    }

    public function test_next_month_from_leap_esfand_30_lands_on_valid_farvardin_day(): void
    {
        $this->personal(['view' => 'month', 'system' => 'jalali', 'year' => '1403', 'month' => '12', 'day' => '30'])
            ->call('next')->assertSet('year', '1404')->assertSet('month', '1')->assertSet('day', '30');
    }
}
