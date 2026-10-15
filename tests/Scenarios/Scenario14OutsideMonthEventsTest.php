<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario14OutsideMonthEventsTest extends ScenarioCase
{
    public function test_events_on_leading_and_trailing_grid_days_are_loaded_and_shown(): void
    {
        // July 2026 starts on Wednesday: the Sunday-first grid begins 2026-06-28 and ends 2026-08-01.
        $this->task('Leading', '2026-06-29 09:00:00', '2026-06-29 10:00:00');
        $this->task('Trailing', '2026-08-01 09:00:00', '2026-08-01 10:00:00');
        $this->task('Beyond', '2026-08-02 09:00:00', '2026-08-02 10:00:00');

        $c = $this->onDay('2026-07-15', 'month');

        $c->assertSee('Leading')->assertSee('Trailing')->assertDontSee('Beyond');
        $this->assertStringContainsString('is-outside', $c->html());
    }
}
