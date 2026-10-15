<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario18StatusAndSearchTest extends ScenarioCase
{
    private function seedTasks(): void
    {
        $this->task('Dentist visit', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->task('Audit', '2026-07-15 11:00:00', '2026-07-15 12:00:00', ['status' => 'locked']);
        $this->task('Report', '2026-07-15 13:00:00', '2026-07-15 14:00:00', ['status' => 'done']);
    }

    public function test_status_filter_narrows_to_the_selected_value(): void
    {
        $this->seedTasks();

        $this->onDay('2026-07-15', 'week')
            ->call('setFilter', 'tasks.status', 'locked')
            ->assertSee('Audit')->assertDontSee('Dentist visit')->assertDontSee('Report')
            ->call('resetFilters')
            ->assertSee('Dentist visit')->assertSee('Audit')->assertSee('Report');
    }

    public function test_forged_filter_value_is_ignored(): void
    {
        $this->seedTasks();

        $this->onDay('2026-07-15', 'week')
            ->call('setFilter', 'tasks.status', "locked' OR 1=1 --")
            ->assertSee('Dentist visit')->assertSee('Audit')
            ->call('setFilter', 'tasks.nonexistent', 'x')
            ->assertSee('Report');
    }

    public function test_search_matches_titles_and_is_length_capped(): void
    {
        $this->seedTasks();

        $this->onDay('2026-07-15', 'week')
            ->set('search', 'dent')->assertSee('Dentist visit')->assertDontSee('Audit')
            ->set('search', '%')->assertDontSee('Dentist visit') // LIKE wildcards are escaped, "%" is literal
            ->set('search', str_repeat('x', 500))->assertOk();
    }
}
