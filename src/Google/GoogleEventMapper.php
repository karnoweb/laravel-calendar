<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Google;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventReference;

/** Google event resource <-> CalendarEventData. Pure functions, no IO. */
final class GoogleEventMapper
{
    /** Google "colorId" => hex (Google Calendar default event palette). */
    public const COLORS = [
        '1' => '#7986cb', '2' => '#33b679', '3' => '#8e24aa', '4' => '#e67c73', '5' => '#f6bf26',
        '6' => '#f4511e', '7' => '#039be5', '8' => '#616161', '9' => '#3f51b5', '10' => '#0b8043', '11' => '#d50000',
    ];

    public const ID_PATTERN = '/^[A-Za-z0-9_-]{1,200}$/';

    /** @param array<string, mixed> $item */
    public function toEvent(array $item, string $sourceKey, ActionCapabilities $capabilities, string $defaultColor = '#1a73e8'): ?CalendarEventData
    {
        $id = $item['id'] ?? null;
        if (! is_string($id) || ! preg_match(self::ID_PATTERN, $id) || ($item['status'] ?? '') === 'cancelled') {
            return null;
        }

        $utc = new DateTimeZone('UTC');
        $startRaw = $item['start'] ?? [];
        $endRaw = $item['end'] ?? [];
        try {
            if (isset($startRaw['date'])) {
                $allDay = true;
                $start = new DateTimeImmutable($startRaw['date'].' 00:00:00', $utc);
                $end = new DateTimeImmutable(($endRaw['date'] ?? $startRaw['date']).' 00:00:00', $utc);
                if ($end <= $start) {
                    $end = $start->modify('+1 day');
                }
            } elseif (isset($startRaw['dateTime'], $endRaw['dateTime'])) {
                $allDay = false;
                $start = (new DateTimeImmutable($startRaw['dateTime']))->setTimezone($utc);
                $end = (new DateTimeImmutable($endRaw['dateTime']))->setTimezone($utc);
                if ($end <= $start) {
                    $end = $start->modify('+30 minutes');
                }
            } else {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $color = isset($item['colorId'], self::COLORS[(string) $item['colorId']]) ? self::COLORS[(string) $item['colorId']] : $defaultColor;

        return new CalendarEventData(
            new EventReference($sourceKey, $id),
            (string) ($item['summary'] ?? '(No title)') === '' ? '(No title)' : (string) ($item['summary'] ?? '(No title)'),
            $start,
            $end,
            $allDay,
            isset($item['etag']) ? (string) $item['etag'] : null,
            $capabilities,
            isset($item['description']) ? (string) $item['description'] : null,
            isset($item['location']) ? (string) $item['location'] : null,
            $color,
            isset($item['htmlLink']) ? (string) $item['htmlLink'] : null,
            isset($item['status']) ? (string) $item['status'] : null,
            'google',
        );
    }

    /** @return array{date?: string, dateTime?: string, timeZone?: string} */
    public function when(DateTimeImmutable $value, bool $allDay, string $timezone): array
    {
        if ($allDay) {
            return ['date' => $value->format('Y-m-d')];
        }

        return ['dateTime' => $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), 'timeZone' => $timezone];
    }
}
