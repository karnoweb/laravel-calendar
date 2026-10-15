<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario22AllDayExclusiveEndTest extends ScenarioCase
{
    public function test_all_day_event_with_exclusive_end_covers_exactly_its_days(): void
    {
        // 10th and 11th; end date 12th is exclusive.
        $this->task('Conference', '2026-07-10 00:00:00', '2026-07-12 00:00:00', ['all_day' => true]);

        foreach (['2026-07-10' => true, '2026-07-11' => true, '2026-07-12' => false, '2026-07-09' => false] as $day => $expected) {
            $c = $this->onDay($day, 'week')->call('openDay', $day);
            $html = $c->html();
            $drawer = substr($html, (int) strpos($html, 'kw-drawer'));
            $this->assertSame($expected, str_contains($drawer, 'Conference'), "day {$day}");
        }
    }

    public function test_all_day_event_is_not_shifted_by_the_viewer_timezone(): void
    {
        $this->task('Holiday', '2026-07-10 00:00:00', '2026-07-11 00:00:00', ['all_day' => true]);

        foreach (['Asia/Tehran', 'Europe/Bucharest', 'UTC'] as $tz) {
            $this->loginAs(self::ACTOR_A, self::TENANT_A, $tz);
            $c = $this->open('personal', ['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '10'])->call('openDay', '2026-07-10');
            $html = $c->html();
            $this->assertStringContainsString('Holiday', substr($html, (int) strpos($html, 'kw-drawer')), $tz);
        }
    }
}
