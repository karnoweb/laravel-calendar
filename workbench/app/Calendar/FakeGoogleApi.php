<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Dev-only stand-in for the Google Calendar REST API, so the Google source can be seen without credentials. */
final class FakeGoogleApi
{
    public static function install(): void
    {
        Http::fake([
            'www.googleapis.com/*' => function (Request $request) {
                $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
                if ($request->method() === 'GET' && ! str_contains($path, '/events/')) {
                    return Http::response(['items' => self::items(), 'etag' => 'x']);
                }
                if (preg_match('#/events/([^/?]+)$#', $path, $m)) {
                    $eventId = urldecode($m[1]);
                    $found = null;
                    foreach (self::items() as $item) {
                        if (($item['id'] ?? '') === $eventId) {
                            $found = $item;
                            break;
                        }
                    }
                    if ($request->method() === 'GET') {
                        return $found !== null
                            ? Http::response($found)
                            : Http::response(['error' => ['code' => 404]], 404);
                    }
                    if ($request->method() === 'DELETE') {
                        return Http::response('', 204);
                    }
                    $body = $request->data();
                    $base = $found ?? ['id' => $eventId, 'summary' => 'Google Event'];

                    return Http::response(array_replace($base, $body, [
                        'id' => $eventId,
                        'etag' => '"'.substr(md5((string) microtime(true)), 0, 12).'"',
                    ]));
                }
                if ($request->method() === 'DELETE') {
                    return Http::response('', 204);
                }
                $body = $request->data();

                return Http::response(['id' => 'fake'.substr(md5((string) microtime(true)), 0, 10), 'etag' => '"'.md5((string) microtime(true)).'"'] + $body);
            },
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function items(): array
    {
        $tz = 'Asia/Tehran';
        $day = fn (int $offset, string $time) => (new \DateTimeImmutable("today {$time}", new \DateTimeZone($tz)))->modify(($offset >= 0 ? '+' : '').$offset.' days')->format('c');
        $date = fn (int $offset) => (new \DateTimeImmutable('today', new \DateTimeZone($tz)))->modify(($offset >= 0 ? '+' : '').$offset.' days')->format('Y-m-d');
        $timed = fn (string $id, string $title, int $d, string $from, string $to, string $color) => [
            'id' => $id, 'summary' => $title, 'etag' => '"'.$id.'"', 'colorId' => $color, 'status' => 'confirmed',
            'start' => ['dateTime' => $day($d, $from)], 'end' => ['dateTime' => $day($d, $to)],
            'htmlLink' => 'https://calendar.google.com/calendar/event?eid='.$id,
        ];

        return [
            $timed('gevent01', 'Google: Team lunch', 0, '12:30', '13:30', '2'),
            $timed('gevent02', 'Google: Product demo', 1, '15:00', '16:30', '7'),
            $timed('gevent03', 'Google: 1:1 with Sara', 2, '10:00', '10:45', '3'),
            $timed('gevent04', 'Google: Flight to IST', 4, '06:00', '09:30', '6'),
            [
                'id' => 'gevent05', 'summary' => 'Google: Conference', 'etag' => '"gevent05"', 'colorId' => '9', 'status' => 'confirmed',
                'start' => ['date' => $date(3)], 'end' => ['date' => $date(6)],
            ],
        ];
    }
}
