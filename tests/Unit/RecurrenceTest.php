<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Calendar\JalaliCalendarSystem;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\RecurrenceExceptionData;
use Karnoweb\Calendar\Data\RecurrenceRuleData;
use Karnoweb\Calendar\Data\RecurrenceSeriesData;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\RecurrenceFrequency;
use Karnoweb\Calendar\Recurrence\RecurrenceExpander;
use Karnoweb\Calendar\Recurrence\RecurrenceLimitPolicy;
use Karnoweb\Calendar\Recurrence\RecurrenceSeriesSplitter;
use PHPUnit\Framework\TestCase;

final class RecurrenceTest extends TestCase
{
    private const TZ = 'Asia/Tehran';

    private function dt(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone(self::TZ));
    }

    private function series(RecurrenceRuleData $rule, string $start = '2026-06-01 09:00:00', array $ex = []): RecurrenceSeriesData
    {
        return new RecurrenceSeriesData('s1', self::TZ, $this->dt($start), $this->dt(substr($start, 0, 11).'10:00:00'), $rule, $ex);
    }

    private function range(string $from, string $to): DateRange
    {
        return DateRange::ofLocalDays($from, $to, self::TZ);
    }

    public function test_weekly_rule_on_selected_weekdays(): void
    {
        $rule = new RecurrenceRuleData(RecurrenceFrequency::Weekly, 1, [1, 3], timezone: self::TZ); // Mon, Wed
        $occ = (new RecurrenceExpander)->expand($this->series($rule), 'tasks', '7', $this->range('2026-06-01', '2026-06-15'));

        $days = array_map(fn ($o) => $o->start->setTimezone(new DateTimeZone(self::TZ))->format('m-d'), $occ);
        $this->assertSame(['06-01', '06-03', '06-08', '06-10'], $days);
    }

    public function test_count_bounds_the_series(): void
    {
        $rule = new RecurrenceRuleData(RecurrenceFrequency::Daily, 1, count: 3, timezone: self::TZ);
        $occ = (new RecurrenceExpander)->expand($this->series($rule), 'tasks', '7', $this->range('2026-06-01', '2026-07-01'));

        $this->assertCount(3, $occ);
    }

    public function test_never_ending_rule_is_still_bounded_by_range_and_cap(): void
    {
        $rule = new RecurrenceRuleData(RecurrenceFrequency::Daily, 1, timezone: self::TZ);
        $occ = (new RecurrenceExpander)->expand($this->series($rule), 'tasks', '7', $this->range('2026-06-01', '2027-05-30'), new RecurrenceLimitPolicy(maxOccurrences: 50));

        $this->assertLessThanOrEqual(50, count($occ));
        $this->assertNotEmpty($occ);
    }

    public function test_cancelled_occurrence_disappears_and_replaced_moves(): void
    {
        $rule = new RecurrenceRuleData(RecurrenceFrequency::Daily, 1, count: 4, timezone: self::TZ);
        $series = $this->series($rule, ex: [
            new RecurrenceExceptionData('2026-06-02T09:00:00', RecurrenceExceptionData::CANCELLED),
            new RecurrenceExceptionData('2026-06-03T09:00:00', RecurrenceExceptionData::REPLACED, [
                'start' => $this->dt('2026-06-03 14:00:00')->format('c'),
                'end' => $this->dt('2026-06-03 15:00:00')->format('c'),
            ]),
        ]);

        $occ = (new RecurrenceExpander)->expand($series, 'tasks', '7', $this->range('2026-06-01', '2026-06-10'));

        $this->assertCount(3, $occ);
        $keys = array_map(fn ($o) => $o->eventRef->occurrenceKey, $occ);
        $this->assertNotContains('2026-06-02T09:00:00', $keys);
        $this->assertContains('2026-06-03T09:00:00', $keys, 'moved occurrence keeps its original identity');
        $replaced = array_values(array_filter($occ, fn ($o) => $o->replaced));
        $this->assertSame('14:00', $replaced[0]->start->setTimezone(new DateTimeZone(self::TZ))->format('H:i'));
    }

    public function test_jalali_monthly_rule_clamps_to_month_length(): void
    {
        $j = new JalaliCalendarSystem;
        $start = $this->dt($j->toIso(1404, 5, 31).' 09:00:00'); // Mordad 31
        $rule = new RecurrenceRuleData(RecurrenceFrequency::Monthly, 1, calendarSystem: CalendarSystem::Jalali, timezone: self::TZ, count: 4);
        $series = new RecurrenceSeriesData('s2', self::TZ, $start, $start->modify('+1 hour'), $rule);

        $occ = (new RecurrenceExpander)->expand($series, 'tasks', '9', $this->range('2025-08-01', '2026-02-01'));

        $dates = array_map(fn ($o) => implode('-', $j->fromIso($o->start->setTimezone(new DateTimeZone(self::TZ))->format('Y-m-d'))), $occ);
        $this->assertSame(['1404-5-31', '1404-6-31', '1404-7-30', '1404-8-30'], $dates);
    }

    public function test_future_split_truncates_old_series_and_rebases_count(): void
    {
        $rule = new RecurrenceRuleData(RecurrenceFrequency::Daily, 1, count: 10, timezone: self::TZ);
        $split = (new RecurrenceSeriesSplitter)->split($this->series($rule), '2026-06-04T09:00:00', 'new-series');

        $this->assertSame(3, $split['before']->rule->count);
        $this->assertSame(7, $split['after']->rule->count);
        $this->assertSame('new-series', $split['after']->seriesId);
        $this->assertSame('2026-06-04 09:00', $split['after']->start->setTimezone(new DateTimeZone(self::TZ))->format('Y-m-d H:i'));
    }

    public function test_split_at_first_occurrence_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RecurrenceSeriesSplitter)->split($this->series(new RecurrenceRuleData(RecurrenceFrequency::Daily, 1, count: 5, timezone: self::TZ)), '2026-06-01T09:00:00', 'x');
    }
}
