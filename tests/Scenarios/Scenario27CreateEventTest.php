<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario27CreateEventTest extends ScenarioCase
{
    public function test_create_stores_the_event_in_the_server_scope_in_utc(): void
    {
        $c = $this->personal(['view' => 'day', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('openCreate', '2026-07-15')
            ->set('form.title', 'New event')->set('form.startTime', '09:00')->set('form.endTime', '10:00')
            ->call('submitForm')->assertSet('formOpen', false)->assertSee('New event');

        $t = DemoTask::query()->where('title', 'New event')->firstOrFail();
        $this->assertSame(self::SCOPE_A, $t->scope_key);
        $this->assertSame('11', (string) $t->owner_id);
        $this->assertSame('2026-07-15 09:00', $this->localTime($t->starts_at));
    }

    public function test_empty_title_is_a_validation_error_and_stores_nothing(): void
    {
        $this->personal(['view' => 'day', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('openCreate', '2026-07-15')->set('form.title', '')->call('submitForm')->assertSet('formOpen', true);

        $this->assertSame(0, DemoTask::query()->count());
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->personal(['view' => 'day', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('openCreate', '2026-07-15')->set('form.title', 'Bad')->set('form.startTime', '10:00')->set('form.endTime', '09:00')
            ->call('submitForm')->assertSet('formOpen', true);

        $this->assertSame(0, DemoTask::query()->count());
    }
}
