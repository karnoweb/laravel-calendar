<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Unit;

use Karnoweb\Calendar\Calendar\CalendarCursor;
use Karnoweb\Calendar\Calendar\GregorianCalendarSystem;
use Karnoweb\Calendar\Calendar\JalaliCalendarSystem;
use Karnoweb\Calendar\Enums\CalendarView;
use PHPUnit\Framework\TestCase;

final class CalendarSystemsTest extends TestCase
{
    public function test_nowruz_1404_is_march_21_2025(): void
    {
        $j = new JalaliCalendarSystem;

        $this->assertSame('2025-03-21', $j->toIso(1404, 1, 1));
        $this->assertSame([1404, 1, 1], $j->fromIso('2025-03-21'));
    }

    public function test_jalali_leap_year_has_30_day_esfand(): void
    {
        $j = new JalaliCalendarSystem;

        $this->assertTrue($j->isLeapYear(1403));
        $this->assertSame(30, $j->daysInMonth(1403, 12));
        $this->assertSame(29, $j->daysInMonth(1404, 12));
        $this->assertTrue($j->isValid(1403, 12, 30));
        $this->assertFalse($j->isValid(1404, 12, 30));
        $this->assertSame('2025-03-20', $j->toIso(1403, 12, 30));
    }

    public function test_gregorian_leap_year(): void
    {
        $g = new GregorianCalendarSystem;

        $this->assertTrue($g->isValid(2028, 2, 29));
        $this->assertFalse($g->isValid(2026, 2, 29));
        $this->assertFalse($g->isValid(2100, 2, 29));
    }

    public function test_jalali_month_step_clamps_day(): void
    {
        $j = new JalaliCalendarSystem;
        $cursor = new CalendarCursor($j);

        $next = $cursor->addMonths($j->toIso(1404, 6, 31), 1);

        $this->assertSame([1404, 7, 30], $j->fromIso($next));
    }

    public function test_cursor_steps_by_view(): void
    {
        $cursor = new CalendarCursor(new GregorianCalendarSystem);

        $this->assertSame('2026-03-01', $cursor->next('2026-02-01', CalendarView::Month));
        $this->assertSame('2026-02-08', $cursor->next('2026-02-01', CalendarView::Week));
        $this->assertSame('2026-02-02', $cursor->next('2026-02-01', CalendarView::Day));
        $this->assertSame('2026-01-31', $cursor->previous('2026-02-01', CalendarView::Day));
    }

    public function test_year_boundary_in_jalali(): void
    {
        $j = new JalaliCalendarSystem;
        $cursor = new CalendarCursor($j);

        $next = $cursor->addMonths($j->toIso(1404, 12, 15), 1);

        $this->assertSame([1405, 1, 15], $j->fromIso($next));
    }
}
