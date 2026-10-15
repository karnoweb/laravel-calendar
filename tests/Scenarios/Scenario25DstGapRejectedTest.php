<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario25DstGapRejectedTest extends ScenarioCase
{
    public function test_nonexistent_local_time_is_rejected_and_nothing_is_stored(): void
    {
        $this->loginAs(self::ACTOR_A, self::TENANT_A, 'Europe/Bucharest');
        $c = $this->open('personal', ['view' => 'day', 'year' => '2026', 'month' => '3', 'day' => '29'])
            ->call('openCreate', '2026-03-29')
            ->set('form.title', 'Gap')
            ->set('form.allDay', false)
            ->set('form.startDate', '2026/03/29')->set('form.startTime', '03:30')
            ->set('form.endDate', '2026/03/29')->set('form.endTime', '04:30')
            ->call('submitForm');

        $c->assertSet('formOpen', true);
        $this->assertNotEmpty($c->get('formErrors'));
        $this->assertSame(0, DemoTask::query()->where('title', 'Gap')->count());
    }

    public function test_a_valid_neighbouring_time_is_accepted(): void
    {
        $this->loginAs(self::ACTOR_A, self::TENANT_A, 'Europe/Bucharest');
        $this->open('personal', ['view' => 'day', 'year' => '2026', 'month' => '3', 'day' => '29'])
            ->call('openCreate', '2026-03-29')
            ->set('form.title', 'Fine')->set('form.allDay', false)
            ->set('form.startDate', '2026/03/29')->set('form.startTime', '04:00')
            ->set('form.endDate', '2026/03/29')->set('form.endTime', '05:00')
            ->call('submitForm')->assertSet('formOpen', false);

        $this->assertSame(1, DemoTask::query()->where('title', 'Fine')->count());
    }
}
