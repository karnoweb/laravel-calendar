<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario34ReadOnlyProviderTest extends ScenarioCase
{
    public function test_reservation_source_cannot_be_deleted_or_edited(): void
    {
        $r = $this->ops('reservation', 'Hall', '2026-07-15 12:00:00', '2026-07-15 13:00:00');
        $this->loginAs(self::ACTOR_A, self::TENANT_A);

        $this->open('operations', ['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestDelete', $this->ref($r->id, 'reservations'), '1')->call('confirmPending')
            ->call('openEdit', $this->ref($r->id, 'reservations'))->assertSet('formOpen', false);

        $this->assertTrue(DemoTask::query()->whereKey($r->id)->exists());
    }

    public function test_public_calendar_is_read_only(): void
    {
        $t = $this->task('Public', '2026-07-15 09:00:00', '2026-07-15 10:00:00', [], self::TZ, 'public');
        $this->logout();

        $this->open('public', ['view' => 'week', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('requestDelete', $this->ref($t->id), '1')->call('confirmPending')
            ->call('openCreate', '2026-07-15')->assertSet('formOpen', false);

        $this->assertTrue(DemoTask::query()->whereKey($t->id)->exists());
    }
}
