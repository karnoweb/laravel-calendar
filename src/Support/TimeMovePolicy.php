<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Calendar\IsoDate;
use Karnoweb\Calendar\Calendar\TimezoneConverter;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;

/**
 * Computes the new start/end of a drag/keyboard move.
 *  - date move (month grid / all-day): keep the local wall clock of BOTH start and end, change only the day.
 *  - time move (week/day grid slot): the dropped slot becomes the new start, the real duration is kept.
 * A resulting local time that does not exist (DST gap) or is ambiguous (fold) is rejected, never shifted.
 */
final class TimeMovePolicy
{
    public function __construct(private readonly TimezoneConverter $converter) {}

    /** @return array{start: DateTimeImmutable, end: DateTimeImmutable} */
    public function moveToDate(CalendarEventData $event, string $targetIso, string $timezone): array
    {
        if ($event->allDay) {
            $days = IsoDate::diffDays($event->start->format('Y-m-d'), $event->end->format('Y-m-d'));
            $tz = new DateTimeZone($timezone);

            return [
                'start' => new DateTimeImmutable($targetIso.' 00:00:00', $tz),
                'end' => new DateTimeImmutable(IsoDate::addDays($targetIso, $days).' 00:00:00', $tz),
            ];
        }

        $startLocal = $event->startAt($timezone);
        $endLocal = $event->endAt($timezone);
        $utc = new DateTimeZone('UTC');
        $wallDuration = (new DateTimeImmutable($endLocal->format('Y-m-d H:i:s'), $utc))->getTimestamp()
            - (new DateTimeImmutable($startLocal->format('Y-m-d H:i:s'), $utc))->getTimestamp();

        $newStartLocal = $targetIso.' '.$startLocal->format('H:i:s');
        $newEndNaive = (new DateTimeImmutable($newStartLocal, $utc))->modify(($wallDuration >= 0 ? '+' : '').$wallDuration.' seconds');

        return [
            'start' => $this->converter->localToUtc($newStartLocal, $timezone),
            'end' => $this->converter->localToUtc($newEndNaive->format('Y-m-d H:i:s'), $timezone),
        ];
    }

    /** @return array{start: DateTimeImmutable, end: DateTimeImmutable} */
    public function moveToTime(CalendarEventData $event, string $targetIso, int $minuteOfDay, string $timezone): array
    {
        if ($event->allDay || $minuteOfDay < 0 || $minuteOfDay > 1439) {
            throw new InvalidCalendarDate('Invalid time slot move.');
        }
        $start = $this->converter->localToUtc(sprintf('%s %02d:%02d:00', $targetIso, intdiv($minuteOfDay, 60), $minuteOfDay % 60), $timezone);
        $seconds = $event->end->getTimestamp() - $event->start->getTimestamp();

        return ['start' => $start, 'end' => $start->modify(($seconds >= 0 ? '+' : '').$seconds.' seconds')];
    }

    /** New end for a resize: snaps to a minute of the (start's local) day or following day, strictly after start. */
    public function resizeEnd(CalendarEventData $event, string $targetIso, int $minuteOfDay, string $timezone): DateTimeImmutable
    {
        if ($event->allDay || $minuteOfDay < 0 || $minuteOfDay > 1440) {
            throw new InvalidCalendarDate('Invalid resize.');
        }
        $end = $minuteOfDay === 1440
            ? $this->converter->localToUtc(IsoDate::addDays($targetIso, 1).' 00:00:00', $timezone)
            : $this->converter->localToUtc(sprintf('%s %02d:%02d:00', $targetIso, intdiv($minuteOfDay, 60), $minuteOfDay % 60), $timezone);
        if ($end <= $event->start) {
            throw new InvalidCalendarDate('Resize would make the event end before it starts.');
        }

        return $end;
    }
}
