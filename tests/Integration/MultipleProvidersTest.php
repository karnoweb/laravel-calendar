<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use Karnoweb\Calendar\Livewire\Calendar;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Tests\WorkbenchTestCase;
use Livewire\Livewire;
use Workbench\App\Models\DemoTask;

final class MultipleProvidersTest extends WorkbenchTestCase
{
    private function seed3(): void
    {
        $day = now('Asia/Tehran')->startOfDay()->setTime(9, 0)->utc();
        foreach (['class' => 'Algebra', 'shift' => 'Night guard', 'reservation' => 'Hall booking'] as $kind => $title) {
            DemoTask::create(['scope_key' => 't101', 'kind' => $kind, 'title' => $title, 'timezone' => 'Asia/Tehran', 'starts_at' => $day, 'ends_at' => $day->copy()->addHour(), 'version' => 1]);
        }
    }

    public function test_three_providers_render_in_one_calendar(): void
    {
        $this->loginAs('11', '101');
        $this->seed3();

        Livewire::test(Calendar::class, ['calendarKey' => 'operations'])
            ->assertSee('Algebra')->assertSee('Night guard')->assertSee('Hall booking');
    }

    public function test_source_toggle_hides_only_that_source(): void
    {
        $this->loginAs('11', '101');
        $this->seed3();

        Livewire::test(Calendar::class, ['calendarKey' => 'operations'])
            ->call('toggleSource', 'shifts')
            ->assertDontSee('Night guard')
            ->assertSee('Algebra')
            ->assertSee('Hall booking');
    }

    public function test_other_tenant_rows_never_appear(): void
    {
        $this->loginAs('11', '101');
        $day = now('Asia/Tehran')->startOfDay()->setTime(9, 0)->utc();
        DemoTask::create(['scope_key' => 't202', 'kind' => 'class', 'title' => 'Foreign class', 'timezone' => 'Asia/Tehran', 'starts_at' => $day, 'ends_at' => $day->copy()->addHour(), 'version' => 1]);

        Livewire::test(Calendar::class, ['calendarKey' => 'operations'])->assertDontSee('Foreign class');
    }

    public function test_read_only_source_rejects_writes_even_if_called_directly(): void
    {
        $this->loginAs('11', '101');
        $this->seed3();
        $class = DemoTask::query()->where('kind', 'class')->firstOrFail();
        $ref = EventReferenceCodec::encode(new EventReference('classes', (string) $class->id));

        Livewire::test(Calendar::class, ['calendarKey' => 'operations'])->call('requestDelete', $ref, '1');

        $this->assertDatabaseHas('demo_tasks', ['id' => $class->id]);
    }
}
