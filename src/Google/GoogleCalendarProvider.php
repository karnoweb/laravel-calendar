<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Google;

use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Exceptions\GoogleCalendarException;

/**
 * Read side of the Google Calendar source.
 *
 * Recurring events are requested with singleEvents=true, so each instance is a normal event
 * (editing one changes that instance only; series editing is not supported).
 * Not connected (no token) => an empty page, not an error.
 */
class GoogleCalendarProvider implements EventProvider
{
    public function __construct(
        protected readonly GoogleCalendarClient $client,
        protected readonly GoogleEventMapper $mapper,
        protected readonly GoogleAccessTokenResolver $tokens,
    ) {}

    protected function calendarId(): string
    {
        return (string) config('calendar.google.calendar_id', 'primary');
    }

    protected function capabilities(): ActionCapabilities
    {
        return (bool) config('calendar.google.writable', false) ? new ActionCapabilities(true, true, true, true, true) : ActionCapabilities::none();
    }

    public function fetch(EventQuery $query, CalendarContext $context): EventPage
    {
        $token = $this->tokens->accessToken($context);
        if ($token === null || $token === '') {
            return EventPage::empty($query->requestKey);
        }

        $params = [
            'timeMin' => $query->range->startInclusive->format('Y-m-d\TH:i:s\Z'),
            'timeMax' => $query->range->endExclusive->format('Y-m-d\TH:i:s\Z'),
            'maxResults' => min($query->limit, 250),
        ];
        if ($query->search !== '') {
            $params['q'] = $query->search;
        }
        if ($query->cursor !== null && preg_match('/^[A-Za-z0-9._~=-]{1,512}$/', $query->cursor)) {
            $params['pageToken'] = $query->cursor;
        }

        $data = $this->client->list($token, $this->calendarId(), $params);
        $events = [];
        foreach ($data['items'] ?? [] as $item) {
            if (is_array($item) && ($e = $this->mapper->toEvent($item, $this->sourceKey(), $this->capabilities(), $this->defaultColor())) !== null) {
                $events[] = $e;
            }
        }
        $next = isset($data['nextPageToken']) && is_string($data['nextPageToken']) ? $data['nextPageToken'] : null;

        return new EventPage($events, $next !== null, $next, null, $query->requestKey);
    }

    public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData
    {
        if ($reference->occurrenceKey !== null || ! preg_match(GoogleEventMapper::ID_PATTERN, $reference->eventId)) {
            return null;
        }
        $token = $this->tokens->accessToken($context);
        if ($token === null || $token === '') {
            return null;
        }
        try {
            $item = $this->client->get($token, $this->calendarId(), $reference->eventId);
        } catch (GoogleCalendarException $e) {
            if ($e->isNotFound()) {
                return null;
            }
            throw $e;
        }

        return $this->mapper->toEvent($item, $reference->sourceKey, $this->capabilities(), $this->defaultColor());
    }

    protected function sourceKey(): string
    {
        return 'google';
    }

    protected function defaultColor(): string
    {
        return '#1a73e8';
    }
}
