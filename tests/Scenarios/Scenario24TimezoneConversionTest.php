<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario24TimezoneConversionTest extends ScenarioCase
{
    public function test_same_instant_is_shown_in_each_viewers_timezone(): void
    {
        // 10:00 Tehran (+03:30) = 06:30 UTC = 09:30 Bucharest (+03:00).
        $this->task('Call', '2026-07-15 10:00:00', '2026-07-15 11:00:00');

        foreach (['Asia/Tehran' => '10:00', 'UTC' => '06:30', 'Europe/Bucharest' => '09:30'] as $tz => $label) {
            $this->loginAs(self::ACTOR_A, self::TENANT_A, $tz);
            $c = $this->open('personal', ['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15']);
            $c->assertSee('Call');
            $this->assertStringContainsString($label, $c->html(), $tz);
        }
    }

    public function test_event_near_midnight_moves_to_the_next_day_for_a_viewer_ahead_of_utc(): void
    {
        // 23:30 UTC on the 15th is 02:30 on the 16th in Bucharest.
        $this->task('Late', '2026-07-15 23:30:00', '2026-07-16 00:30:00', [], 'UTC');
        $this->loginAs(self::ACTOR_A, self::TENANT_A, 'Europe/Bucharest');

        $c = $this->open('personal', ['view' => 'day', 'year' => '2026', 'month' => '7', 'day' => '16']);
        $c->assertSee('Late');
    }
}
