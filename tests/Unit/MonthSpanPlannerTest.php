<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Unit;

use Karnoweb\Calendar\Calendar\MonthSpanPlanner;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventReference;
use DateTimeImmutable;
use Karnoweb\Calendar\Tests\TestCase;

final class MonthSpanPlannerTest extends TestCase
{
    public function test_places_multi_day_event_across_columns(): void
    {
        $event = $this->event('Trip', '2026-07-10', '2026-07-12', allDay: true);
        $week = ['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10', '2026-07-11', '2026-07-12'];
        $plan = (new MonthSpanPlanner)->plan([$event], $week, 'UTC');

        $this->assertCount(1, $plan);
        $this->assertSame(4, $plan[0]['colStart']);
        $this->assertSame(2, $plan[0]['colSpan']);
        $this->assertSame(0, $plan[0]['lane']);
    }

    public function test_skips_single_day_timed_events(): void
    {
        $event = $this->event('Meet', '2026-07-10 10:00:00', '2026-07-10 11:00:00');
        $week = ['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10', '2026-07-11', '2026-07-12'];
        $plan = (new MonthSpanPlanner)->plan([$event], $week, 'UTC');

        $this->assertSame([], $plan);
    }

    private function event(string $title, string $start, string $end, bool $allDay = false): CalendarEventData
    {
        return new CalendarEventData(
            new EventReference('tasks', '1'),
            $title,
            new DateTimeImmutable($start, new \DateTimeZone('UTC')),
            new DateTimeImmutable($end, new \DateTimeZone('UTC')),
            $allDay,
            '1',
            new ActionCapabilities,
        );
    }
}
