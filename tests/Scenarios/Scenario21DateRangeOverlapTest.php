<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario21DateRangeOverlapTest extends ScenarioCase
{
    public function test_range_is_half_open_events_crossing_midnight_overlap_but_touching_ones_do_not(): void
    {
        // Tehran is +03:30 in July; local times below.
        $this->task('Crosses midnight', '2026-07-14 23:00:00', '2026-07-15 01:00:00');
        $this->task('Ends at midnight', '2026-07-14 22:00:00', '2026-07-15 00:00:00');
        $this->task('Starts at midnight', '2026-07-15 00:00:00', '2026-07-15 00:30:00');
        $this->task('Next day', '2026-07-16 00:00:00', '2026-07-16 01:00:00');

        $c = $this->onDay('2026-07-15', 'week')->call('openDay', '2026-07-15');

        $c->assertSee('Crosses midnight')->assertSee('Starts at midnight');
        $html = $c->html();
        $drawerHtml = substr($html, (int) strpos($html, 'kw-drawer'));
        $this->assertStringNotContainsString('Ends at midnight', $drawerHtml);
        $this->assertStringNotContainsString('Next day', $drawerHtml);
    }
}
