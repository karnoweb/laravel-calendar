<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use Karnoweb\Calendar\Livewire\Calendar;
use Karnoweb\Calendar\Tests\Scenarios\ScenarioCase;
use Livewire\Livewire;
use Workbench\App\Models\DemoTask;

final class MovabilityAndQuickMoveTest extends ScenarioCase
{
    public function test_global_config_disables_move_and_resize(): void
    {
        config(['calendar.features.move' => false, 'calendar.features.resize' => false]);

        $t = $this->task('Config locked', '2026-07-15 10:00:00', '2026-07-15 11:00:00');

        $this->personal(['view' => 'month', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestMove', $this->ref($t->id), '2026-07-20', null, 'op-cfg-move', '1')
            ->call('confirmPending');

        $t->refresh();
        $this->assertSame('2026-07-15 10:00', $this->localTime($t->starts_at));
    }

    public function test_embed_movable_false_disables_move_and_resize(): void
    {
        $this->loginAs('11', '101');
        $t = $this->task('Embed locked', '2026-07-15 10:00:00', '2026-07-15 11:00:00');

        Livewire::test(Calendar::class, [
            'calendarKey' => 'personal',
            'movable' => false,
            'year' => '2026',
            'month' => '7',
            'day' => '15',
        ])
            ->assertSet('movingEnabled', false)
            ->call('requestMove', $this->ref($t->id), '2026-07-20', null, 'op-embed-move', '1');

        $t->refresh();
        $this->assertSame('2026-07-15 10:00', $this->localTime($t->starts_at));
    }

    public function test_runtime_toggle_moving_blocks_move_when_disabled(): void
    {
        $t = $this->task('Runtime toggle', '2026-07-15 10:00:00', '2026-07-15 11:00:00');

        $this->personal(['view' => 'month', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('toggleMoving')
            ->assertSet('movingEnabled', false)
            ->call('requestMove', $this->ref($t->id), '2026-07-20', null, 'op-rt-move', '1');

        $t->refresh();
        $this->assertSame('2026-07-15 10:00', $this->localTime($t->starts_at));
    }

    public function test_per_task_movable_flag_blocks_move_and_can_be_toggled(): void
    {
        $t = $this->task('Per-task locked', '2026-07-15 10:00:00', '2026-07-15 11:00:00', ['movable' => false]);
        $ref = $this->ref($t->id);

        $component = $this->personal(['view' => 'month', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestMove', $ref, '2026-07-20', null, 'op-task-lock-1', '1')
            ->call('confirmPending');

        $t->refresh();
        $this->assertSame('2026-07-15 10:00', $this->localTime($t->starts_at));

        // Unlock the task via toggleEventMovable and move it
        $component
            ->call('toggleEventMovable', $ref, '1')
            ->call('requestMove', $ref, '2026-07-20', null, 'op-task-lock-2', '2')
            ->call('confirmPending');

        $t->refresh();
        $this->assertTrue($t->movable);
        $this->assertSame('2026-07-20 10:00', $this->localTime($t->starts_at));
    }

    public function test_quick_move_changes_date_and_time_in_jalali_and_gregorian(): void
    {
        $t = $this->task('Quick move task', '2026-07-15 09:00:00', '2026-07-15 10:30:00');
        $ref = $this->ref($t->id);

        $this->personal(['view' => 'month', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('openEvent', $ref)
            ->set('quickMoveDate', '2026-07-22')
            ->set('quickMoveTime', '15:30')
            ->call('quickMove', $ref, '1');

        $t->refresh();
        $this->assertSame('2026-07-22 15:30', $this->localTime($t->starts_at));
        $this->assertSame('2026-07-22 17:00', $this->localTime($t->ends_at));

        // Now move via Jalali date (1405-04-29 = 2026-07-20)
        $this->personal(['view' => 'month', 'system' => 'jalali', 'year' => '1405', 'month' => '4', 'day' => '24'])
            ->call('openEvent', $ref)
            ->set('quickMoveDate', '1405-04-29')
            ->set('quickMoveTime', '18:00')
            ->call('quickMove', $ref, '2');

        $t->refresh();
        $this->assertSame('2026-07-20 18:00', $this->localTime($t->starts_at));
        $this->assertSame('2026-07-20 19:30', $this->localTime($t->ends_at));
    }
}
