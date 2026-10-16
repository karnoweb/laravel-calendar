<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario23MultiDayEventTest extends ScenarioCase
{
    public function test_multi_day_event_renders_start_middle_and_end_segments(): void
    {
        $this->task('Camp', '2026-07-10 20:00:00', '2026-07-12 08:00:00');

        $html = $this->onDay('2026-07-15', 'month')->html();
        $this->assertStringContainsString('kw-month__span', $html);
        $this->assertStringContainsString('Camp', $html);
        $this->assertGreaterThanOrEqual(1, substr_count($html, 'kw-event--start'));
    }
}
