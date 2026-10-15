<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoRecurringSeries;

final class Scenario42RecurringAllEditTest extends ScenarioCase
{
    public function test_all_scope_edit_changes_the_series_title(): void
    {
        $s = $this->series('Daily', '2026-07-13 10:00:00', '2026-07-13 11:00:00', ['frequency' => 'DAILY', 'count' => 3]);

        $c = $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('openEdit', $this->ref($s->id, 'tasks', '2026-07-14T10:00:00'))
            ->call('chooseScope', 'all')
            ->assertSet('formOpen', true)
            ->set('form.title', 'Renamed series')
            ->call('submitForm')->assertSet('formOpen', false);

        $this->assertSame('Renamed series', $s->fresh()->title);
        $c->assertSee('Renamed series');
    }
}
