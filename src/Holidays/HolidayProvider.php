<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Holidays;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Support\IsoDate;
use Morilog\Jalali\CalendarUtils;

/**
 * Read-only "official holidays" source driven entirely by config('calendar.holidays').
 *
 * - `jalali`:    fixed solar-calendar holidays, list of [month, day, 'English title', 'عنوان فارسی'].
 * - `gregorian`: fixed Gregorian holidays, same shape.
 * - `dates`:     one-off absolute dates (use for lunar holidays, which move every year):
 *                ['date' => 'YYYY-MM-DD', 'title' => '...', 'title_fa' => '...'].
 *
 * Nothing here is fetched from the network. Moving (lunar) holidays are NOT built in; the host supplies them in `dates`.
 */
class HolidayProvider implements EventProvider
{
    public const SOURCE_KEY = 'holidays';

    public function fetch(EventQuery $query, CalendarContext $context): EventPage
    {
        $tz = new DateTimeZone($context->timezone);
        $day = $query->range->startInclusive->setTimezone($tz)->setTime(0, 0);
        $end = $query->range->endExclusive->setTimezone($tz);
        $fa = $context->locale === 'fa';

        $fixedJ = $this->index((array) config('calendar.holidays.jalali', []));
        $fixedG = $this->index((array) config('calendar.holidays.gregorian', []));
        $dates = [];
        foreach ((array) config('calendar.holidays.dates', []) as $row) {
            if (is_array($row) && isset($row['date'], $row['title']) && is_string($row['date'])) {
                $dates[$row['date']][] = $fa && ! empty($row['title_fa']) ? (string) $row['title_fa'] : (string) $row['title'];
            }
        }

        $events = [];
        $guard = 0;
        while ($day < $end && $guard++ < 800) {
            $iso = $day->format('Y-m-d');
            [$gy, $gm, $gd] = [(int) $day->format('Y'), (int) $day->format('n'), (int) $day->format('j')];
            [, $jm, $jd] = CalendarUtils::toJalali($gy, $gm, $gd);
            $titles = array_merge($fixedJ["{$jm}-{$jd}"] ?? [], $fixedG["{$gm}-{$gd}"] ?? [], $dates[$iso] ?? []);
            foreach (array_values(array_unique($fa ? $this->pick($titles, true) : $this->pick($titles, false))) as $i => $title) {
                $events[] = new CalendarEventData(
                    new EventReference(self::SOURCE_KEY, "{$iso}-{$i}"),
                    $title,
                    new DateTimeImmutable($iso.' 00:00:00', $tz),
                    (new DateTimeImmutable($iso.' 00:00:00', $tz))->modify('+1 day'),
                    true,
                    null,
                    ActionCapabilities::none(),
                    null,
                    null,
                    '#d93025',
                    null,
                    null,
                    'holiday',
                );
            }
            $day = $day->modify('+1 day');
        }

        return new EventPage($events, false, null, null, $query->requestKey);
    }

    public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData
    {
        if (! preg_match('/^(\d{4}-\d{2}-\d{2})-(\d+)$/', $reference->eventId, $m)) {
            return null;
        }
        $iso = $m[1];
        $range = DateRange::ofLocalDays($iso, IsoDate::addDays($iso, 1), $context->timezone);
        $page = $this->fetch(new EventQuery($range, 50), $context);
        foreach ($page->events as $event) {
            if ($event->reference->eventId === $reference->eventId) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Index "month-day" => list of ['en' => ..., 'fa' => ...]. Titles are kept as pairs until pick().
     *
     * @param  array<int, mixed>  $rows
     * @return array<string, list<array{0:string,1:string}>>
     */
    private function index(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (is_array($r) && count($r) >= 3) {
                $out[((int) $r[0]).'-'.((int) $r[1])][] = [(string) $r[2], (string) ($r[3] ?? $r[2])];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{0:string,1:string}|string>  $titles
     * @return list<string>
     */
    private function pick(array $titles, bool $fa): array
    {
        return array_map(fn ($t) => is_array($t) ? ($fa ? $t[1] : $t[0]) : $t, $titles);
    }
}
