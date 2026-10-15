<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Livewire\Calendar;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Tests\FeatureTestCase;
use Karnoweb\Calendar\Tests\Fixtures\FakeStore;
use Livewire\Livewire;

final class Scenario49UnknownOutcomeTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeStore::add('7', 'Standup', '2026-07-15 10:00:00', '2026-07-15 11:00:00');
    }

    public function test_handler_failure_is_reported_as_unknown_never_as_success(): void
    {
        FakeStore::$throwOnExecute = true;
        $ref = EventReferenceCodec::encode(new EventReference('tasks', '7'));

        Livewire::test(Calendar::class, ['calendarKey' => 'personal'])->set('view', 'week')
            ->call('requestDelete', $ref, '1')->call('confirmPending')
            ->assertSet('outcomeState', 'unknown')->assertSee('Outcome unknown')->assertDontSee('Deleted');

        $this->assertArrayHasKey('7', FakeStore::$rows);
    }
}
