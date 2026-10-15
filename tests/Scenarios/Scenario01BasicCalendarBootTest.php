<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario01BasicCalendarBootTest extends ScenarioCase
{
    public function test_registered_calendar_renders_its_events(): void
    {
        $this->task('Standup', '2026-07-15 09:00:00', '2026-07-15 10:00:00');

        $this->onDay('2026-07-15', 'list')
            ->assertSee('Standup')
            ->assertSee('kw-calendar', false);
    }

    public function test_unknown_calendar_key_shows_no_events_and_a_denied_state(): void
    {
        $this->task('Standup', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_A);

        $this->open('not-registered')
            ->assertSee('You do not have access to this calendar.')
            ->assertDontSee('Standup');
    }
}
