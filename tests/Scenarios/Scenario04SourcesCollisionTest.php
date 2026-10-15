<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Support\CalendarManager;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Tests\Fixtures\CollisionCalendarFactory;

final class Scenario04SourcesCollisionTest extends ScenarioCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(CalendarManager::class)->register('collision', CollisionCalendarFactory::class);
        $this->loginAs(self::ACTOR_A, self::TENANT_A);
    }

    public function test_same_event_id_in_two_sources_stays_two_independent_events(): void
    {
        $this->open('collision', ['view' => 'list', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->assertSee('Task #1')->assertSee('Shift #1');
    }

    public function test_identity_is_composite_and_never_prefix_parsed(): void
    {
        $a = EventReferenceCodec::encode(new EventReference('tasks', '1'));
        $b = EventReferenceCodec::encode(new EventReference('shifts', '1'));
        $c = EventReferenceCodec::encode(new EventReference('shifts', 'task:1'));

        $this->assertNotSame($a, $b);
        $decoded = EventReferenceCodec::tryDecode($c);
        $this->assertSame(['shifts', 'task:1'], [$decoded->sourceKey, $decoded->eventId], 'an id that looks like "task:1" must stay intact');
        $this->assertTrue(EventReferenceCodec::tryDecode($a)->equals(new EventReference('tasks', '1')));
        $this->assertFalse(EventReferenceCodec::tryDecode($a)->equals(EventReferenceCodec::tryDecode($b)));
    }

    public function test_details_open_the_event_of_the_requested_source(): void
    {
        $ref = EventReferenceCodec::encode(new EventReference('shifts', '1'));

        $this->open('collision', ['view' => 'list', 'year' => '2026', 'month' => '7', 'day' => '15'])
            ->call('openEvent', $ref)
            ->assertSee('Shift #1')
            ->assertSet('detailsRef', $ref);
    }
}
