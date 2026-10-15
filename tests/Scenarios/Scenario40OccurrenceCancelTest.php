<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoRecurringSeries;

final class Scenario40OccurrenceCancelTest extends ScenarioCase
{
    public function test_this_only_delete_hides_exactly_one_occurrence(): void
    {
        $s = $this->series('Daily', '2026-07-13 10:00:00', '2026-07-13 11:00:00', ['frequency' => 'DAILY', 'count' => 5]);

        $c = $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15']);
        $c->call('requestDelete', $this->ref($s->id, 'tasks', '2026-07-14T10:00:00'), '1')->call('chooseScope', 'this')->call('confirmPending');

        $keys = array_map(fn ($e) => $e->reference->occurrenceKey, $c->viewData('read')->events);
        $this->assertNotContains('2026-07-14T10:00:00', $keys);
        $this->assertContains('2026-07-13T10:00:00', $keys);
        $this->assertContains('2026-07-15T10:00:00', $keys);
    }

    public function test_cancelling_the_scope_prompt_changes_nothing(): void
    {
        $s = $this->series('Daily', '2026-07-13 10:00:00', '2026-07-13 11:00:00', ['frequency' => 'DAILY', 'count' => 5]);

        $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestDelete', $this->ref($s->id, 'tasks', '2026-07-14T10:00:00'), '1')
            ->assertNotSet('scopePrompt', null)
            ->call('cancelScope')->assertSet('scopePrompt', null);

        $this->assertSame([], $s->fresh()->exceptions);
    }
}
