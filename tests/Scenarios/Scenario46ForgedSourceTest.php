<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario46ForgedSourceTest extends ScenarioCase
{
    public function test_ref_with_unknown_or_foreign_source_is_rejected(): void
    {
        $t = $this->task('Mine', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $c = $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15']);

        foreach (['payroll', 'shifts', 'classes'] as $source) {
            $c->call('requestDelete', $this->ref($t->id, $source), '1')->assertSet('pending', null);
        }
        $c->call('requestDelete', 'not-a-valid-ref', '1')->assertSet('pending', null);

        $this->assertTrue(DemoTask::query()->whereKey($t->id)->exists());
    }

    public function test_create_with_a_forged_source_never_writes_to_it(): void
    {
        $this->personal(['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('openCreate', '2026-07-15', 'payroll')
            ->set('form.title', 'Forged')->call('submitForm');

        $this->assertSame(0, DemoTask::query()->where('title', 'Forged')->where('kind', '!=', 'task')->count());
    }
}
