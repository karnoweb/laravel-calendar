<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Google;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Karnoweb\Calendar\Exceptions\GoogleCalendarException;

/**
 * Thin Google Calendar REST v3 client. The access token is passed in per call and never stored.
 * Calendar id and base URL come from server config, never from the browser.
 */
final class GoogleCalendarClient
{
    private function request(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('calendar.google.timeout', 8))
            ->retry(1, 150, throw: false);
    }

    private function url(string $calendarId, string $suffix = ''): string
    {
        return rtrim((string) config('calendar.google.api_base', 'https://www.googleapis.com/calendar/v3'), '/')
            .'/calendars/'.rawurlencode($calendarId).'/events'.$suffix;
    }

    /** @param array<string, scalar> $query @return array<string, mixed> */
    public function list(string $token, string $calendarId, array $query): array
    {
        return $this->decode($this->request($token)->get($this->url($calendarId), $query + ['singleEvents' => 'true', 'orderBy' => 'startTime']));
    }

    /** @return array<string, mixed> */
    public function get(string $token, string $calendarId, string $eventId): array
    {
        return $this->decode($this->request($token)->get($this->url($calendarId, '/'.rawurlencode($eventId))));
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function insert(string $token, string $calendarId, array $body): array
    {
        return $this->decode($this->request($token)->post($this->url($calendarId), $body));
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function patch(string $token, string $calendarId, string $eventId, array $body, ?string $etag): array
    {
        $req = $this->request($token);
        if ($etag !== null && $etag !== '') {
            $req = $req->withHeaders(['If-Match' => $etag]);
        }

        return $this->decode($req->patch($this->url($calendarId, '/'.rawurlencode($eventId)), $body));
    }

    public function delete(string $token, string $calendarId, string $eventId, ?string $etag): void
    {
        $req = $this->request($token);
        if ($etag !== null && $etag !== '') {
            $req = $req->withHeaders(['If-Match' => $etag]);
        }
        $this->decode($req->delete($this->url($calendarId, '/'.rawurlencode($eventId))));
    }

    /** @return array<string, mixed> */
    private function decode(\Illuminate\Http\Client\Response $response): array
    {
        if ($response->failed()) {
            // Never put the response body (may echo user data / tokens) into the message.
            throw new GoogleCalendarException('Google Calendar request failed.', $response->status());
        }

        return $response->json() ?? [];
    }
}
