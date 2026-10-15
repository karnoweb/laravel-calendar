<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario07GregorianLeapYearTest extends ScenarioCase
{
    public function test_feb_29_exists_in_a_leap_year(): void
    {
        $this->task('Leap day', '2028-02-29 09:00:00', '2028-02-29 10:00:00');

        $this->personal(['view' => 'month', 'system' => 'gregorian', 'year' => '2028', 'month' => '2', 'day' => '29'])
            ->assertSet('day', '29')
            ->assertSee('Leap day');
    }

    public function test_feb_29_is_clamped_in_a_common_year(): void
    {
        $this->personal(['view' => 'month', 'system' => 'gregorian', 'year' => '2026', 'month' => '2', 'day' => '29'])
            ->call('next')->call('previous')
            ->assertSet('month', '2')
            ->assertSet('day', '28');
    }
}
