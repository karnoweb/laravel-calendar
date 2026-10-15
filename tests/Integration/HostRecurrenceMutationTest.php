<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Livewire\Calendar;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Tests\WorkbenchTestCase;
use Livewire\Livewire;
use Workbench\App\Models\DemoRecurringSeries;

final class HostRecurrenceMutationTest extends WorkbenchTestCase
{
    private function series(int $count = 6): DemoRecurringSeries
    {
        $start = now('Asia/Tehran')->startOfDay()->setTime(10, 0)->utc();

        return DemoRecurringSeries::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'title' => 'Sync', 'timezone' => 'Asia/Tehran',
            'starts_at' => $start, 'ends_at' => $start->copy()->addHour(),
            'rule' => ['frequency' => 'DAILY', 'interval' => 1, 'byWeekday' => [], 'count' => $count, 'calendarSystem' => 'gregorian', 'timezone' => 'Asia/Tehran'],
            'exceptions' => [], 'version' => 1,
        ]);
    }

    private function occ(DemoRecurringSeries $s, int $dayOffset): string
    {
        $key = now('Asia/Tehran')->startOfDay()->addDays($dayOffset)->format('Y-m-d').'T10:00:00';

        return EventReferenceCodec::encode(new EventReference('tasks', (string) $s->id, $key));
    }

    private function cal()
    {
        $this->loginAs('11', '101', 'Asia/Tehran');

        return Livewire::test(Calendar::class, ['calendarKey' => 'personal'])->set('view', 'list');
    }

    public function test_this_only_delete_cancels_one_occurrence(): void
    {
        $s = $this->series();

        $this->cal()->call('requestDelete', $this->occ($s, 2), '1')->call('chooseScope', 'this')->call('confirmPending');

        $s->refresh();
        $this->assertCount(1, $s->exceptions);
        $this->assertSame('cancelled', $s->exceptions[0]['kind']);
        $this->assertSame(2, $s->version);
    }

    public function test_future_delete_truncates_the_series(): void
    {
        $s = $this->series();

        $this->cal()->call('requestDelete', $this->occ($s, 3), '1')->call('chooseScope', 'future')->call('confirmPending');

        $s->refresh();
        $this->assertSame(3, $s->rule['count']);
        $this->assertSame(1, DemoRecurringSeries::count());
    }

    public function test_all_delete_removes_the_series(): void
    {
        $s = $this->series();

        $this->cal()->call('requestDelete', $this->occ($s, 1), '1')->call('chooseScope', 'all')->call('confirmPending');

        $this->assertSame(0, DemoRecurringSeries::count());
    }

    public function test_future_move_splits_into_two_series_atomically(): void
    {
        $s = $this->series(6);
        $target = now('Asia/Tehran')->startOfDay()->addDays(3)->format('Y-m-d');

        $this->cal()
            ->call('requestMove', $this->occ($s, 2), $target, 15 * 60, 'op-future-1', '1')
            ->call('chooseScope', 'future')
            ->call('confirmPending');

        $this->assertSame(2, DemoRecurringSeries::count());
        $before = DemoRecurringSeries::find($s->id);
        $after = DemoRecurringSeries::where('id', '!=', $s->id)->firstOrFail();
        $this->assertSame(2, $before->rule['count']);
        $this->assertSame(4, $after->rule['count']);
        $this->assertSame('15:00', $after->starts_at->copy()->setTimezone('Asia/Tehran')->format('H:i'));
    }

    public function test_all_move_shifts_whole_series(): void
    {
        $s = $this->series(3);
        $target = now('Asia/Tehran')->startOfDay()->addDays(1)->format('Y-m-d');

        $this->cal()
            ->call('requestMove', $this->occ($s, 0), $target, 8 * 60, 'op-all-1', '1')
            ->call('chooseScope', 'all')
            ->call('confirmPending');

        $s->refresh();
        $this->assertSame('08:00', $s->starts_at->copy()->setTimezone('Asia/Tehran')->format('H:i'));
        $this->assertSame(now('Asia/Tehran')->startOfDay()->addDay()->format('Y-m-d'), $s->starts_at->copy()->setTimezone('Asia/Tehran')->format('Y-m-d'));
    }

    public function test_create_weekly_series_through_form(): void
    {
        $today = now('Asia/Tehran')->format('Y-m-d');

        $this->cal()
            ->call('openCreate', $today, null, 9 * 60)
            ->set('form.title', 'Weekly standup')
            ->set('form.recurrenceFreq', 'WEEKLY')
            ->set('form.recurrenceWeekdays', [1, 3])
            ->set('form.recurrenceEnd', 'count')
            ->set('form.recurrenceCount', 4)
            ->call('submitForm')
            ->assertSet('formOpen', false);

        $s = DemoRecurringSeries::firstOrFail();
        $this->assertSame('WEEKLY', $s->rule['frequency']);
        $this->assertSame(4, $s->rule['count']);
    }
}
