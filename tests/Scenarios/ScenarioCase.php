<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Livewire\Calendar;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Tests\WorkbenchTestCase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Models\DemoRecurringSeries;
use Workbench\App\Models\DemoTask;

/**
 * Shared fixture for the 56 documented scenarios (see each ScenarioNN_*.md).
 * Fixed identities: actorA=11, actorB=22, tenantA=101, tenantB=202. Zones: Asia/Tehran, Europe/Bucharest, UTC.
 */
abstract class ScenarioCase extends WorkbenchTestCase
{
    protected const ACTOR_A = '11';

    protected const ACTOR_B = '22';

    protected const TENANT_A = '101';

    protected const TENANT_B = '202';

    protected const TZ = 'Asia/Tehran';

    /** Personal scope of actorA/tenantA. */
    protected const SCOPE_A = 't101:u11';

    /** Create a standalone task; start/end are LOCAL wall-clock times in $tz. */
    protected function task(string $title, string $startLocal, string $endLocal, array $extra = [], string $tz = self::TZ, string $scope = self::SCOPE_A): DemoTask
    {
        $allDay = (bool) ($extra['all_day'] ?? false);
        $toDb = fn (string $local) => $allDay
            ? substr($local, 0, 10).' 00:00:00'
            : (new \DateTimeImmutable($local, new \DateTimeZone($tz)))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return DemoTask::create($extra + [
            'scope_key' => $scope, 'owner_id' => self::ACTOR_A, 'kind' => 'task', 'title' => $title, 'timezone' => $tz,
            'starts_at' => $toDb($startLocal), 'ends_at' => $toDb($endLocal), 'version' => 1,
        ]);
    }

    /** Operations-calendar row (class | shift | reservation) in tenantA scope. */
    protected function ops(string $kind, string $title, string $startLocal, string $endLocal, string $scope = 't101'): DemoTask
    {
        return $this->task($title, $startLocal, $endLocal, ['kind' => $kind, 'owner_id' => null], self::TZ, $scope);
    }

    /** @param array<string, mixed> $rule */
    protected function series(string $title, string $startLocal, string $endLocal, array $rule, string $tz = self::TZ, array $extra = []): DemoRecurringSeries
    {
        $utc = fn (string $l) => (new \DateTimeImmutable($l, new \DateTimeZone($tz)))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return DemoRecurringSeries::create($extra + [
            'scope_key' => self::SCOPE_A, 'owner_id' => self::ACTOR_A, 'title' => $title, 'timezone' => $tz,
            'starts_at' => $utc($startLocal), 'ends_at' => $utc($endLocal),
            'rule' => $rule + ['interval' => 1, 'byWeekday' => [], 'calendarSystem' => 'gregorian', 'timezone' => $tz],
            'exceptions' => [], 'version' => 1,
        ]);
    }

    /** Open the calendar as actorA/tenantA. $props set URL-bound state, e.g. ['view' => 'week', 'year' => '2026']. */
    protected function personal(array $props = [], string $tz = self::TZ): Testable
    {
        $this->loginAs(self::ACTOR_A, self::TENANT_A, $tz);

        return $this->open('personal', $props);
    }

    protected function open(string $key, array $props = []): Testable
    {
        $c = Livewire::test(Calendar::class, ['calendarKey' => $key]);
        foreach ($props as $name => $value) {
            $c->set($name, $value);
        }

        return $c;
    }

    /** Show a given Gregorian day (Y-m-d) in the given view. */
    protected function onDay(string $iso, string $view = 'month', string $system = 'gregorian'): Testable
    {
        [$y, $m, $d] = explode('-', $iso);

        return $this->personal(['view' => $view, 'system' => $system, 'year' => $y, 'month' => (string) (int) $m, 'day' => (string) (int) $d]);
    }

    /** Assert that raw HTML fragments appear in the given order. */
    protected function assertHtmlOrder(Testable $c, array $needles): void
    {
        $html = $c->html();
        $last = -1;
        foreach ($needles as $needle) {
            $pos = strpos($html, $needle, $last + 1);
            $this->assertNotFalse($pos, "missing or out of order: {$needle}");
            $last = $pos;
        }
    }

    protected function ref(int|string $id, string $source = 'tasks', ?string $occurrence = null): string
    {
        return EventReferenceCodec::encode(new EventReference($source, (string) $id, $occurrence));
    }

    protected function localTime(\DateTimeInterface $utc, string $tz = self::TZ, string $format = 'Y-m-d H:i'): string
    {
        return \DateTimeImmutable::createFromInterface($utc)->setTimezone(new \DateTimeZone($tz))->format($format);
    }
}
