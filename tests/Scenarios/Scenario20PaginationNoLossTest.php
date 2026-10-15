<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario20PaginationNoLossTest extends ScenarioCase
{
    public function test_load_more_reaches_every_event_exactly_once(): void
    {
        $total = 330;
        $rows = [];
        for ($i = 0; $i < $total; $i++) {
            $rows[] = [
                'scope_key' => self::SCOPE_A, 'owner_id' => '11', 'kind' => 'task', 'title' => sprintf('Bulk %03d', $i), 'status' => 'open',
                'timezone' => self::TZ, 'all_day' => false, 'version' => 1,
                'starts_at' => '2026-07-15 05:00:00', 'ends_at' => '2026-07-15 05:30:00', 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DemoTask::insert($chunk);
        }

        $c = $this->onDay('2026-07-15', 'list');
        $first = count($c->viewData('read')->events);
        $this->assertLessThan($total, $first, 'first page must be capped');
        $this->assertTrue($c->viewData('read')->hasMore);

        for ($i = 0; $i < 10 && $c->viewData('read')->hasMore; $i++) {
            $c->call('loadMore');
        }

        $events = $c->viewData('read')->events;
        $ids = array_map(fn ($e) => $e->reference->eventId, $events);
        $this->assertCount($total, $events);
        $this->assertCount($total, array_unique($ids), 'no duplicates across pages');
        $this->assertFalse($c->viewData('read')->hasMore);
    }

    public function test_page_count_from_browser_is_clamped(): void
    {
        $this->onDay('2026-07-15', 'list')->set('pages', 999999)->assertOk();
    }
}
