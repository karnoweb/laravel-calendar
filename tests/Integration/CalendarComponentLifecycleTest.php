<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use Karnoweb\Calendar\Livewire\Calendar;
use Karnoweb\Calendar\Tests\WorkbenchTestCase;
use Livewire\Livewire;
use Workbench\Database\Seeders\CalendarDemoSeeder;

final class CalendarComponentLifecycleTest extends WorkbenchTestCase
{
    public function test_seeded_personal_calendar_renders_only_own_data(): void
    {
        $this->seed(CalendarDemoSeeder::class);
        $this->loginAs('11', '101');

        Livewire::test(Calendar::class, ['calendarKey' => 'personal'])
            ->set('view', 'list')
            ->assertSee('Standup')
            ->assertDontSee('Private B');
    }

    public function test_actor_b_sees_only_own_data(): void
    {
        $this->seed(CalendarDemoSeeder::class);
        $this->loginAs('22', '101');

        Livewire::test(Calendar::class, ['calendarKey' => 'personal'])
            ->set('view', 'list')
            ->assertSee('Private B')
            ->assertDontSee('Standup');
    }

    public function test_locked_task_cannot_be_deleted(): void
    {
        $this->seed(CalendarDemoSeeder::class);
        $this->loginAs('11', '101');
        $locked = \Workbench\App\Models\DemoTask::where('status', 'locked')->firstOrFail();
        $ref = \Karnoweb\Calendar\Support\EventReferenceCodec::encode(new \Karnoweb\Calendar\Data\EventReference('tasks', (string) $locked->id));

        Livewire::test(Calendar::class, ['calendarKey' => 'personal'])->call('requestDelete', $ref, '1');

        $this->assertDatabaseHas('demo_tasks', ['id' => $locked->id]);
    }

    public function test_session_change_between_requests_is_honoured(): void
    {
        $this->seed(CalendarDemoSeeder::class);
        $this->loginAs('11', '101');
        $component = Livewire::test(Calendar::class, ['calendarKey' => 'personal'])->set('view', 'list')->assertSee('Standup');

        $this->logout();

        $component->call('refreshNow')->assertSee('You do not have access to this calendar.')->assertDontSee('Standup');
    }

    public function test_url_state_round_trips(): void
    {
        $this->loginAs('11', '101');

        Livewire::test(Calendar::class, ['calendarKey' => 'personal'])
            ->call('setView', 'week')
            ->assertSet('view', 'week')
            ->call('setSystem', 'jalali')
            ->assertSet('system', 'jalali');
    }

    public function test_invalid_calendar_key_is_404(): void
    {
        $this->loginAs('11', '101');

        Livewire::test(Calendar::class, ['calendarKey' => 'Bad Key!'])->assertStatus(404);
    }
}
