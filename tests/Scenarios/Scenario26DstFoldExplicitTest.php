<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario26DstFoldExplicitTest extends ScenarioCase
{
    public function test_ambiguous_local_time_requires_an_explicit_offset(): void
    {
        $this->loginAs(self::ACTOR_A, self::TENANT_A, 'Europe/Bucharest');
        $c = $this->open('personal', ['view' => 'day', 'year' => '2026', 'month' => '10', 'day' => '25'])
            ->call('openCreate', '2026-10-25')
            ->set('form.title', 'Fold')->set('form.allDay', false)
            ->set('form.startDate', '2026/10/25')->set('form.startTime', '03:30')
            ->set('form.endDate', '2026/10/25')->set('form.endTime', '03:45')
            ->call('submitForm')
            ->assertSet('formOpen', true);

        $this->assertSame(0, DemoTask::query()->where('title', 'Fold')->count(), 'nothing may be guessed and stored');

        $offsets = $c->get('formOffsets');
        $this->assertEqualsCanonicalizing(['+03:00', '+02:00'], $offsets['startOffset']);

        $c->set('form.startOffset', '+03:00')->set('form.endOffset', '+03:00')
            ->call('submitForm')->assertSet('formOpen', false);

        $task = DemoTask::query()->where('title', 'Fold')->firstOrFail();
        $this->assertSame('2026-10-25 00:30:00', $task->starts_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_offset_that_is_not_a_valid_candidate_is_rejected(): void
    {
        $this->loginAs(self::ACTOR_A, self::TENANT_A, 'Europe/Bucharest');
        $this->open('personal', ['view' => 'day', 'year' => '2026', 'month' => '10', 'day' => '25'])
            ->call('openCreate', '2026-10-25')
            ->set('form.title', 'Forged')->set('form.allDay', false)
            ->set('form.startDate', '2026/10/25')->set('form.startTime', '03:30')
            ->set('form.endDate', '2026/10/25')->set('form.endTime', '03:45')
            ->set('form.startOffset', '+09:00')->set('form.endOffset', '+09:00')
            ->call('submitForm')->assertSet('formOpen', true);

        $this->assertSame(0, DemoTask::query()->where('title', 'Forged')->count());
    }
}
