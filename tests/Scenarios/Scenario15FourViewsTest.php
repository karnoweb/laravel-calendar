<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

final class Scenario15FourViewsTest extends ScenarioCase
{
    public function test_every_view_renders_the_same_event_with_its_own_layout(): void
    {
        $this->task('Planning', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $markers = ['month' => 'kw-month', 'week' => 'kw-week', 'day' => 'kw-week', 'list' => 'data-kw-virtual-list'];

        foreach ($markers as $view => $marker) {
            $c = $this->onDay('2026-07-15', $view);
            $this->assertStringContainsString($marker, $c->html(), "view {$view}");
            if ($view !== 'list') { // the list view renders rows client-side from JSON
                $c->assertSee('Planning');
            } else {
                $this->assertStringContainsString('Planning', $c->html());
            }
        }
    }

    public function test_switching_views_keeps_the_date(): void
    {
        $this->onDay('2026-07-15', 'month')
            ->call('setView', 'week')->assertSet('view', 'week')->assertSet('day', '15')
            ->call('setView', 'day')->assertSet('view', 'day')->assertSet('day', '15')
            ->call('setView', 'list')->assertSet('view', 'list')
            ->call('setView', 'bogus')->assertSet('view', 'list');
    }

    public function test_day_view_has_one_column_and_week_view_has_seven(): void
    {
        $this->assertCount(1, $this->onDay('2026-07-15', 'day')->viewData('columns'));
        $this->assertCount(7, $this->onDay('2026-07-15', 'week')->viewData('columns'));
    }
}
