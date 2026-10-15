<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario28UpdateEventTest extends ScenarioCase
{
    public function test_update_changes_the_row_and_bumps_the_version(): void
    {
        $t = $this->task('Old', '2026-07-15 09:00:00', '2026-07-15 10:00:00');

        $this->personal(['view' => 'day', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('openEdit', $this->ref($t->id))
            ->assertSet('formOpen', true)->assertSet('form.title', 'Old')
            ->set('form.title', 'Renamed')->call('submitForm')->assertSet('formOpen', false)->assertSee('Renamed');

        $t->refresh();
        $this->assertSame('Renamed', $t->title);
        $this->assertSame(2, (int) $t->version);
    }
}
