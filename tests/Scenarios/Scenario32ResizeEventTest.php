<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario32ResizeEventTest extends ScenarioCase
{
    public function test_resize_changes_only_the_end(): void
    {
        $t = $this->task('Stretch', '2026-07-15 09:00:00', '2026-07-15 10:00:00');

        $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestResize', $this->ref($t->id), '2026-07-15', 705, 'op-resize-1', '1')
            ->call('confirmPending');

        $t->refresh();
        $this->assertSame('2026-07-15 09:00', $this->localTime($t->starts_at));
        $this->assertSame('2026-07-15 11:45', $this->localTime($t->ends_at));
    }
}
