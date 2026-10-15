<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario08WeekStartsTest extends ScenarioCase
{
    public function test_gregorian_week_starts_on_sunday_by_default(): void
    {
        $c = $this->personal(['view' => 'month', 'system' => 'gregorian', 'year' => '2026', 'month' => '7', 'day' => '15']);

        $this->assertHtmlOrder($c, ['title="Sunday"', 'title="Monday"', 'title="Saturday"']);
    }

    public function test_jalali_week_starts_on_saturday(): void
    {
        $c = $this->personal(['view' => 'month', 'system' => 'jalali', 'year' => '1405', 'month' => '4', 'day' => '24']);

        $this->assertHtmlOrder($c, ['title="Saturday"', 'title="Sunday"', 'title="Friday"']);
    }

    public function test_first_weekday_is_configurable(): void
    {
        config(['calendar.first_weekday_gregorian' => 1]);

        $c = $this->personal(['view' => 'month', 'system' => 'gregorian', 'year' => '2026', 'month' => '7', 'day' => '15']);

        $this->assertHtmlOrder($c, ['title="Monday"', 'title="Tuesday"', 'title="Sunday"']);
    }

    public function test_weekend_days_follow_the_system(): void
    {
        $g = $this->personal(['view' => 'week', 'system' => 'gregorian', 'year' => '2026', 'month' => '7', 'day' => '15']);
        $this->assertSame([0, 6], $g->viewData('weekendDays'));

        $j = $this->personal(['view' => 'week', 'system' => 'jalali', 'year' => '1405', 'month' => '4', 'day' => '24']);
        $this->assertSame([5], $j->viewData('weekendDays'));
    }
}
