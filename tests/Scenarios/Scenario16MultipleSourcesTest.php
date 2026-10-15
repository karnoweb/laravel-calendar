<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario16MultipleSourcesTest extends ScenarioCase
{
    public function test_each_source_has_its_own_chip_and_color(): void
    {
        $this->ops('class', 'Math', '2026-07-15 08:00:00', '2026-07-15 09:00:00');
        $this->ops('shift', 'Night', '2026-07-15 10:00:00', '2026-07-15 11:00:00');
        $this->ops('reservation', 'Hall', '2026-07-15 12:00:00', '2026-07-15 13:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_A);

        $c = $this->open('operations', ['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15']);

        $c->assertSee('Classes')->assertSee('Shifts')->assertSee('Reservations')
            ->assertSee('Math')->assertSee('Night')->assertSee('Hall');
        foreach (['green', 'orange', 'purple'] as $color) {
            $this->assertStringContainsString("kw-calendar-color-{$color}", $c->html());
        }
    }

    public function test_host_card_override_is_used_for_the_operations_calendar(): void
    {
        $this->ops('class', 'Math', '2026-07-15 08:00:00', '2026-07-15 09:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_A);

        $this->open('operations', ['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->assertSee('★ Math')
            ->assertSee('data-custom-card', false);
    }
}
