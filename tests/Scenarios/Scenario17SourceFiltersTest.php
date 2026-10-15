<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario17SourceFiltersTest extends ScenarioCase
{
    private function props(): array
    {
        return ['view' => 'list', 'year' => '2026', 'month' => '7', 'day' => '15'];
    }

    public function test_toggle_source_off_and_on(): void
    {
        $this->ops('class', 'Math', '2026-07-15 08:00:00', '2026-07-15 09:00:00');
        $this->ops('shift', 'Night', '2026-07-15 10:00:00', '2026-07-15 11:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_A);

        $this->open('operations', $this->props())
            ->call('toggleSource', 'shifts')
            ->assertSet('disabledSources', ['shifts'])
            ->assertSee('Math')->assertDontSee('Night')
            ->call('toggleSource', 'shifts')
            ->assertSet('disabledSources', [])
            ->assertSee('Night');
    }

    public function test_unknown_or_forged_source_keys_are_ignored(): void
    {
        $this->loginAs(self::ACTOR_A, self::TENANT_A);

        $this->open('operations', $this->props())
            ->call('toggleSource', 'payroll')
            ->assertSet('disabledSources', []);
    }

    public function test_forged_disabled_sources_from_the_url_are_cleaned(): void
    {
        $this->ops('class', 'Math', '2026-07-15 08:00:00', '2026-07-15 09:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_A);

        $this->open('operations', $this->props() + ['disabledSources' => ['payroll', 'shifts']])
            ->assertSee('Math'); // "payroll" does not exist: it must not blank the calendar or raise
    }
}
