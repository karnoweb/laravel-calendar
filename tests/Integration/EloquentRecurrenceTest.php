<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use Karnoweb\Calendar\Actions\ReadDispatcher;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\ReadRequest;
use Karnoweb\Calendar\Enums\RecurrenceScope;
use Karnoweb\Calendar\Livewire\FullCalendar;
use Karnoweb\Calendar\Support\CalendarRuntime;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Tests\WorkbenchTestCase;
use Livewire\Livewire;
use Workbench\App\Models\Meeting;

final class EloquentRecurrenceTest extends WorkbenchTestCase
{
    public function test_changing_one_meeting_occurrence_leaves_the_series_title_intact(): void
    {
        $this->loginAs('11', '101', 'UTC');
        $meeting = Meeting::create([
            'scope_key' => 't101:u11',
            'owner_id' => '11',
            'title' => 'Weekly sync',
            'timezone' => 'UTC',
            'starts_at' => '2026-10-05 09:00:00',
            'ends_at' => '2026-10-05 10:00:00',
            'rule' => ['frequency' => 'WEEKLY', 'interval' => 1, 'byWeekday' => [1], 'count' => 4, 'calendarSystem' => 'gregorian', 'timezone' => 'UTC'],
            'exceptions' => [],
            'version' => 1,
        ]);
        Meeting::create([
            'scope_key' => 't999:u1',
            'title' => 'Other tenant sync',
            'timezone' => 'UTC',
            'starts_at' => '2026-10-05 09:00:00',
            'ends_at' => '2026-10-05 10:00:00',
            'rule' => ['frequency' => 'WEEKLY', 'interval' => 1, 'byWeekday' => [1], 'count' => 4, 'calendarSystem' => 'gregorian', 'timezone' => 'UTC'],
            'exceptions' => [],
            'version' => 1,
        ]);

        $second = EventReferenceCodec::encode(new EventReference('meetings', (string) $meeting->id, '2026-10-12T09:00:00'));
        Livewire::test(FullCalendar::class, ['calendarKey' => 'crm.calendar'])
            ->call('openEdit', $second)
            ->call('chooseScope', RecurrenceScope::This->value)
            ->set('form.title', 'Only this')
            ->call('submitForm');

        $meeting->refresh();
        $this->assertSame('Weekly sync', $meeting->title);
        $this->assertCount(1, $meeting->exceptions);
        $this->assertSame('replaced', $meeting->exceptions[0]['kind']);
        $this->assertSame('Only this', $meeting->exceptions[0]['replacement']['title']);
        $this->assertSame(1, Meeting::query()->where('scope_key', 't101:u11')->count());

        $resolved = app(CalendarRuntime::class)->resolve('crm.calendar');
        $read = app(ReadDispatcher::class)->read(
            $resolved->definition,
            $resolved->context,
            new ReadRequest(DateRange::ofLocalDays('2026-10-01', '2026-11-01', 'UTC')),
        );
        $titles = [];
        foreach ($read->events as $event) {
            if ($event->reference->sourceKey === 'meetings' && $event->reference->eventId === (string) $meeting->id) {
                $titles[$event->reference->occurrenceKey] = $event->title;
            }
            $this->assertNotSame('Other tenant sync', $event->title);
        }
        $this->assertSame('Only this', $titles['2026-10-12T09:00:00']);
        $this->assertSame('Weekly sync', $titles['2026-10-05T09:00:00']);
        $this->assertSame('Weekly sync', $titles['2026-10-19T09:00:00']);
        $this->assertSame('Weekly sync', $titles['2026-10-26T09:00:00']);
    }
}
