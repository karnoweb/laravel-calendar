<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Illuminate\Support\Facades\DB;

final class Scenario19NoFilterRuleTest extends ScenarioCase
{
    public function test_no_selected_filter_means_no_filtering(): void
    {
        $this->task('A', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->task('B', '2026-07-15 11:00:00', '2026-07-15 12:00:00', ['status' => 'locked']);

        $c = $this->onDay('2026-07-15', 'week');

        $c->assertSee('A')->assertSee('B')->assertSet('filters', []);
        $this->assertFalse($c->viewData('hasActiveFilters'));
    }

    public function test_all_sources_off_shows_empty_filtered_state_without_touching_host_data(): void
    {
        $this->task('A', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $c = $this->onDay('2026-07-15', 'week');
        DB::enableQueryLog();
        DB::flushQueryLog();

        $c->call('toggleSource', 'tasks')->assertSee('No events match the current filters.');

        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'demo_tasks') || str_contains($q['query'], 'demo_recurring_series')));
        $c->call('resetFilters')->assertSee('A');
    }
}
