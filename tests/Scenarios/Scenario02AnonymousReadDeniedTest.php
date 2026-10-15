<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Illuminate\Support\Facades\DB;

final class Scenario02AnonymousReadDeniedTest extends ScenarioCase
{
    public function test_guest_is_denied_and_the_provider_is_never_queried(): void
    {
        $this->task('Private', '2026-07-15 09:00:00', '2026-07-15 10:00:00');
        $this->logout();
        DB::enableQueryLog();

        $this->open('personal')->assertSee('You do not have access to this calendar.')->assertDontSee('Private');

        $touched = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'demo_tasks'));
        $this->assertCount(0, $touched, 'denied reads must not reach the host data');
    }

    public function test_public_calendar_is_readable_by_guests_only_with_both_opt_ins(): void
    {
        $this->ops('class', 'Open day', '2026-07-15 10:00:00', '2026-07-15 12:00:00', 'public');
        $this->logout();

        $this->open('public', ['view' => 'list', 'year' => '2026', 'month' => '7', 'day' => '15'])->assertSee('Open day');

        config(['calendar.security.allow_public_calendars' => false]);
        $this->open('public', ['view' => 'list', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->assertSee('You do not have access to this calendar.')
            ->assertDontSee('Open day');
    }
}
