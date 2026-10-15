<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoRecurringSeries;

final class Scenario38RecurringJalaliTest extends ScenarioCase
{
    public function test_monthly_jalali_series_lands_on_the_first_of_each_jalali_month(): void
    {
        // 1405/01/01 = 2026-03-21 ; Tir 1 = 2026-06-22
        $this->series('Rent', '2026-03-21 10:00:00', '2026-03-21 11:00:00', ['frequency' => 'MONTHLY', 'interval' => 1, 'count' => 6, 'calendarSystem' => 'jalali']);

        $c = $this->onDay('2026-06-22', 'month');
        $days = array_map(fn ($e) => $e->startAt(self::TZ)->format('Y-m-d'), $c->viewData('read')->events);

        $this->assertContains('2026-06-22', $days);
        $this->assertNotContains('2026-06-21', $days);
        $this->assertNotContains('2026-06-21', $days);
    }
}
