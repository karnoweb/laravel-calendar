<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\DemoRecurringSeries;
use Workbench\App\Models\DemoTask;
use Workbench\App\Models\EducationalClass;
use Workbench\App\Models\Meeting;
use Workbench\App\Models\Task;

/** Deterministic demo data. Fixed fixture identities: actorA=11, actorB=22, tenantA=101, tenantB=202. */
class CalendarDemoSeeder extends Seeder
{
    public function run(): void
    {
        DemoTask::query()->delete();
        DemoRecurringSeries::query()->delete();
        Task::query()->delete();
        EducationalClass::query()->delete();
        Meeting::query()->delete();

        $today = now('Asia/Tehran')->startOfDay();
        $dbTz = (string) config('app.timezone', 'UTC');
        $at = fn (int $dayOffset, int $h, int $m = 0) => $today->copy()->addDays($dayOffset)->setTime($h, $m)->setTimezone($dbTz);
        $dayDate = fn (int $dayOffset) => $today->copy()->addDays($dayOffset)->format('Y-m-d').' 00:00:00';

        // actor A (tenant A) personal calendar (DemoTask)
        foreach ([[0, 9, 'Standup'], [0, 14, 'Design review'], [1, 11, 'Dentist'], [3, 16, 'Plan sprint']] as [$d, $h, $title]) {
            DemoTask::create([
                'scope_key' => 't101:u11', 'owner_id' => '11', 'kind' => 'task', 'title' => $title, 'timezone' => 'Asia/Tehran',
                'starts_at' => $at($d, $h), 'ends_at' => $at($d, $h + 1), 'version' => 1,
            ]);
        }
        DemoTask::create(['scope_key' => 't101:u11', 'owner_id' => '11', 'kind' => 'task', 'title' => 'Locked audit', 'status' => 'locked', 'movable' => false, 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(2, 10), 'ends_at' => $at(2, 11), 'version' => 1]);

        // Rich Persian RTL demo tasks (timed, multi-day spans, and per-task locked)
        DemoTask::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'kind' => 'task', 'title' => 'جلسه هماهنگی محصول', 'color' => '#3b82f6', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(1, 16, 0), 'ends_at' => $at(1, 17, 30), 'movable' => true, 'version' => 1,
        ]);
        DemoTask::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'kind' => 'task', 'title' => 'بررسی طراحی UI/UX', 'color' => '#8b5cf6', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(2, 14, 0), 'ends_at' => $at(2, 15, 30), 'movable' => true, 'version' => 1,
        ]);
        DemoTask::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'kind' => 'task', 'title' => 'ارائه به مشتری (قفل‌شده)', 'color' => '#f59e0b', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(5, 11, 0), 'ends_at' => $at(5, 12, 30), 'movable' => false, 'version' => 1,
        ]);
        DemoTask::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'kind' => 'task', 'title' => 'سفر کاری مشهد', 'color' => '#8b5cf6', 'all_day' => true, 'timezone' => 'Asia/Tehran',
            'starts_at' => $dayDate(-5), 'ends_at' => $dayDate(-2), 'movable' => true, 'version' => 1,
        ]);
        DemoTask::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'kind' => 'task', 'title' => 'مرخصی پایان هفته', 'color' => '#10b981', 'all_day' => true, 'timezone' => 'Asia/Tehran',
            'starts_at' => $dayDate(4), 'ends_at' => $dayDate(7), 'movable' => true, 'version' => 1,
        ]);

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
            DemoTask::create(['scope_key' => 't101', 'kind' => $kind, 'title' => $title, 'color' => null, 'timezone' => 'Asia/Tehran',
                'starts_at' => $at(0, $h), 'ends_at' => $at(0, $h + 2), 'version' => 1]);
        }
        DemoTask::create(['scope_key' => 't202', 'kind' => 'class', 'title' => 'Other tenant class', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(0, 8), 'ends_at' => $at(0, 10), 'version' => 1]);

        // public read-only calendar data
        DemoTask::create(['scope_key' => 'public', 'kind' => 'class', 'title' => 'Open day', 'timezone' => 'Asia/Tehran',
            'starts_at' => $at(1, 10), 'ends_at' => $at(1, 12), 'version' => 1]);

        // Trait-backed Models: Task (scheduled_for), EducationalClass (starts_at/ends_at), Meeting (starts_at/ends_at)
        Task::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'tenant_id' => '101',
            'title' => 'تحویل گزارش فصلی', 'status' => 'open', 'scheduled_for' => $at(0, 9, 30),
            'timezone' => 'Asia/Tehran', 'movable' => true, 'version' => 1,
        ]);
        Task::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'tenant_id' => '101',
            'title' => 'بررسی کدهای اسپرینت', 'status' => 'done', 'scheduled_for' => $at(1, 11, 0),
            'timezone' => 'Asia/Tehran', 'movable' => true, 'version' => 1,
        ]);

        EducationalClass::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'tenant_id' => '101',
            'title' => 'کلاس ریاضی پیشرفته ۱۰۱', 'instructor_name' => 'دکتر رضایی', 'location' => 'کلاس ۳۰۲',
            'status' => 'scheduled', 'category' => 'math',
            'starts_at' => $at(0, 8, 0), 'ends_at' => $at(0, 10, 0),
            'timezone' => 'Asia/Tehran', 'movable' => true, 'version' => 1,
        ]);
        EducationalClass::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'tenant_id' => '101',
            'title' => 'کارگاه فیزیک کوانتوم', 'instructor_name' => 'دکتر محمدی', 'location' => 'آزمایشگاه مرکزی',
            'status' => 'scheduled', 'category' => 'science',
            'starts_at' => $at(2, 13, 0), 'ends_at' => $at(2, 15, 0),
            'timezone' => 'Asia/Tehran', 'movable' => true, 'version' => 1,
        ]);

        Meeting::create([
            'scope_key' => 't101:u11', 'owner_id' => '11', 'tenant_id' => '101',
            'title' => 'جلسه شورای آموزشی', 'location' => 'اتاق جلسات طبقه ۳',
            'status' => 'scheduled',
            'starts_at' => $at(1, 15, 0), 'ends_at' => $at(1, 16, 30),
            'timezone' => 'Asia/Tehran', 'movable' => true, 'version' => 1,
        ]);
    }
}
