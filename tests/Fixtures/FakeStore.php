<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Fixtures;

use DateTimeImmutable;
use DateTimeZone;

/** In-memory event store for tests. Rows are scoped: provider/handler only see their own scope. */
final class FakeStore
{
    /** @var array<string, array<string, mixed>> */
    public static array $rows = [];

    public static int $fetchCalls = 0;

    public static ?string $currentActor = '11';

    public static string $currentScope = 'tenant:101';

    public static bool $failFetch = false;

    public static bool $throwOnExecute = false;

    /** @var list<string> */
    public static array $executed = [];

    public static function reset(): void
    {
        self::$rows = [];
        self::$fetchCalls = 0;
        self::$currentActor = '11';
        self::$currentScope = 'tenant:101';
        self::$failFetch = false;
        self::$throwOnExecute = false;
        self::$executed = [];
    }

    /** @param array<string, mixed> $extra */
    public static function add(string $id, string $title, string $start, string $end, string $tz = 'Asia/Tehran', ?string $scope = null, bool $allDay = false, array $extra = []): void
    {
        $zone = new DateTimeZone($tz);
        self::$rows[$id] = [
            'id' => $id,
            'scope' => $scope ?? self::$currentScope,
            'title' => $title,
            'start' => new DateTimeImmutable($start, $zone),
            'end' => new DateTimeImmutable($end, $zone),
            'allDay' => $allDay,
            'version' => '1',
            'canEdit' => true,
            'recurring' => false,
        ] + $extra;
    }
}
