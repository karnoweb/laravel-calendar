<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\DemoRecurringSeries;
use Workbench\App\Models\DemoTask;

/** Deterministic demo data. Fixed fixture identities: actorA=11, actorB=22, tenantA=101, tenantB=202. */
class CalendarDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = now('Asia/Tehran')->startOfDay();
        $at = fn (int $dayOffset, int $h, int $m = 0) => $today->copy()->addDays($dayOffset)->setTime($h, $m)->utc();

        // actor A (tenant A) personal calendar
        foreach ([[0, 9, 'Standup'], [0, 14, 'Design review'], [1, 11, 'Dentist'], [3, 16, 'Plan sprint']] as [$d, $h, $title]) {
            DemoTask::create([
                'scope_key' => 't101:u11', 'owner_id' => '11', 'kind' => 'task', 'title' => $title, 'timezone' => 'Asia/Tehran',
                'starts_at' => $at($d, $h), 'ends_at' => $at($d, $h + 1), 'version' => 1,
            ]);
        }
        DemoTask::create(['scope_key' => 't101:u11', 'owner_id' => '11', 'kind' => 'task', 'title' => 'Locked audit', 'status' => 'locked', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(2, 10), 'ends_at' => $at(2, 11), 'version' => 1]);

        DemoRecurringSeries::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'title' => 'Weekly sync', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(0, 10), 'ends_at' => $at(0, 11),
            'rule' => ['frequency' => 'WEEKLY', 'interval' => 1, 'byWeekday' => [], 'calendarSystem' => 'gregorian', 'timezone' => 'Asia/Tehran'],
            'exceptions' => [], 'version' => 1,
        ]);

        // actor B (tenant A) must never see actor A's personal data
        DemoTask::create(['scope_key' => 't101:u22', 'owner_id' => '22', 'kind' => 'task', 'title' => 'Private B', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(0, 9), 'ends_at' => $at(0, 10), 'version' => 1]);

        // operations (tenant A) and another tenant
        foreach ([['class', 'Math 101', 8, 'green'], ['class', 'Physics 201', 13, 'green'], ['shift', 'Morning shift', 7, 'orange'], ['reservation', 'Room A booking', 15, 'purple']] as [$kind, $title, $h, $c]) {
            DemoTask::create(['scope_key' => 't101', 'kind' => $kind, 'title' => $title, 'color' => $c === 'green' ? null : null, 'timezone' => 'Asia/Tehran',
                'starts_at' => $at(0, $h), 'ends_at' => $at(0, $h + 2), 'version' => 1]);
        }
        DemoTask::create(['scope_key' => 't202', 'kind' => 'class', 'title' => 'Other tenant class', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(0, 8), 'ends_at' => $at(0, 10), 'version' => 1]);

        // public read-only calendar data
        DemoTask::create(['scope_key' => 'public', 'kind' => 'class', 'title' => 'Open day', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(1, 10), 'ends_at' => $at(1, 12), 'version' => 1]);
    }
}
