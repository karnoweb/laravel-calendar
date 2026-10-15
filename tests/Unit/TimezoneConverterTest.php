<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Unit;

use Karnoweb\Calendar\Calendar\TimezoneConverter;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;
use PHPUnit\Framework\TestCase;

final class TimezoneConverterTest extends TestCase
{
    public function test_regular_time_converts_to_utc(): void
    {
        $utc = (new TimezoneConverter)->localToUtc('2026-07-01 10:00', 'Asia/Tehran');

        $this->assertSame('2026-07-01 06:30:00', $utc->format('Y-m-d H:i:s'));
    }

    public function test_dst_gap_is_rejected_with_gap_reason(): void
    {
        try {
            (new TimezoneConverter)->localToUtc('2026-03-29 03:30', 'Europe/Bucharest');
            $this->fail('gap time must be rejected');
        } catch (InvalidCalendarDate $e) {
            $this->assertSame(InvalidCalendarDate::GAP, $e->reason);
        }
    }

    public function test_dst_fold_is_rejected_without_offset(): void
    {
        $this->expectException(InvalidCalendarDate::class);

        (new TimezoneConverter)->localToUtc('2026-10-25 03:30', 'Europe/Bucharest');
    }

    public function test_dst_fold_resolves_with_explicit_offset(): void
    {
        $c = new TimezoneConverter;

        $first = $c->localToUtc('2026-10-25 03:30', 'Europe/Bucharest', '+03:00');
        $second = $c->localToUtc('2026-10-25 03:30', 'Europe/Bucharest', '+02:00');

        $this->assertSame('2026-10-25 00:30:00', $first->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 01:30:00', $second->format('Y-m-d H:i:s'));
        $this->assertSame(['+03:00', '+02:00'], $c->candidateOffsets('2026-10-25 03:30', 'Europe/Bucharest'));
    }

    public function test_wrong_offset_for_fold_is_rejected(): void
    {
        $this->expectException(InvalidCalendarDate::class);

        (new TimezoneConverter)->localToUtc('2026-10-25 03:30', 'Europe/Bucharest', '+05:00');
    }

    public function test_malformed_input_is_rejected(): void
    {
        $this->expectException(InvalidCalendarDate::class);

        (new TimezoneConverter)->localToUtc('2026-02-30 10:00', 'UTC');
    }
}
