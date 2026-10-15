<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Google;

use Karnoweb\Calendar\Contracts\ActionHandler;
use Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CreateEventInput;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\MoveEventInput;
use Karnoweb\Calendar\Data\ResizeEventInput;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Exceptions\GoogleCalendarException;

/**
 * Write side of the Google source. Opt-in (`calendar.google.writable`).
 *
 * Optimistic concurrency uses the Google event ETag as the version (If-Match -> HTTP 412 => Conflict).
 * A failure after the request left the server is surfaced as an exception so the dispatcher records
 * UnknownOutcome rather than a false success.
 */
class GoogleCalendarHandler implements ActionHandler
{
    public function __construct(
        protected readonly GoogleCalendarClient $client,
        protected readonly GoogleEventMapper $mapper,
        protected readonly GoogleAccessTokenResolver $tokens,
    ) {}

    public function execute(ActionCommand $command, CalendarContext $context): ActionResult
    {
        if (! (bool) config('calendar.google.writable', false)) {
            return ActionResult::failure(ActionStatus::ReadOnly);
        }
        $token = $this->tokens->accessToken($context);
        if ($token === null || $token === '') {
            return ActionResult::failure(ActionStatus::Forbidden);
        }
        $calendarId = (string) config('calendar.google.calendar_id', 'primary');
        $ref = $command->reference;
        if ($ref !== null && (! preg_match(GoogleEventMapper::ID_PATTERN, $ref->eventId) || $ref->occurrenceKey !== null)) {
            return ActionResult::failure(ActionStatus::NotFound);
        }

        try {
            return match ($command->action) {
                CalendarAction::Create => $this->create($token, $calendarId, $command, $context),
                CalendarAction::Update => $this->update($token, $calendarId, $command, $context, $ref),
                CalendarAction::Move => $this->move($token, $calendarId, $command, $context, $ref),
                CalendarAction::Resize => $this->resize($token, $calendarId, $command, $context, $ref),
                CalendarAction::Delete => $this->delete($token, $calendarId, $command, $ref),
                default => ActionResult::failure(ActionStatus::Unsupported),
            };
        } catch (GoogleCalendarException $e) {
            return match (true) {
                $e->isConflict() => ActionResult::failure(ActionStatus::Conflict),
                $e->isNotFound() => ActionResult::failure(ActionStatus::NotFound),
                $e->isAuth() => ActionResult::failure(ActionStatus::Forbidden),
                $e->status === 429 || $e->status >= 500 => ActionResult::failure(ActionStatus::RetryableError),
                default => throw $e,
            };
        }
    }

    private function create(string $token, string $calendarId, ActionCommand $c, CalendarContext $ctx): ActionResult
    {
        $in = CreateEventInput::fromCommand($c, $ctx->timezone);
        if ($in->recurrence !== null) {
            return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.unsupported');
        }
        $created = $this->client->insert($token, $calendarId, [
            'summary' => $in->title,
            'description' => $in->description,
            'location' => $in->location,
            'start' => $this->mapper->when($in->start, $in->allDay, $in->timezone),
            'end' => $this->mapper->when($in->end, $in->allDay, $in->timezone),
        ]);

        return ActionResult::success([new EventReference('google', (string) ($created['id'] ?? ''))], newVersion: isset($created['etag']) ? (string) $created['etag'] : null);
    }

    private function update(string $token, string $calendarId, ActionCommand $c, CalendarContext $ctx, ?EventReference $ref): ActionResult
    {
        $in = CreateEventInput::fromCommand($c, $ctx->timezone);
        if ($in->recurrence !== null) {
            return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.unsupported');
        }
        $res = $this->client->patch($token, $calendarId, $ref->eventId, [
            'summary' => $in->title, 'description' => $in->description, 'location' => $in->location,
            'start' => $this->mapper->when($in->start, $in->allDay, $in->timezone),
            'end' => $this->mapper->when($in->end, $in->allDay, $in->timezone),
        ], $c->expectedVersion);

        return ActionResult::success([$ref], newVersion: isset($res['etag']) ? (string) $res['etag'] : null);
    }

    private function move(string $token, string $calendarId, ActionCommand $c, CalendarContext $ctx, ?EventReference $ref): ActionResult
    {
        $m = MoveEventInput::fromCommand($c, $ctx->timezone);
        $res = $this->client->patch($token, $calendarId, $ref->eventId, [
            'start' => $this->mapper->when($m->start, $m->allDay, $m->timezone),
            'end' => $this->mapper->when($m->end, $m->allDay, $m->timezone),
        ], $c->expectedVersion);

        return ActionResult::success([$ref], newVersion: isset($res['etag']) ? (string) $res['etag'] : null);
    }

    private function resize(string $token, string $calendarId, ActionCommand $c, CalendarContext $ctx, ?EventReference $ref): ActionResult
    {
        $r = ResizeEventInput::fromCommand($c, $ctx->timezone);
        $res = $this->client->patch($token, $calendarId, $ref->eventId, [
            'end' => $this->mapper->when($r->end, false, $r->timezone),
        ], $c->expectedVersion);

        return ActionResult::success([$ref], newVersion: isset($res['etag']) ? (string) $res['etag'] : null);
    }

    private function delete(string $token, string $calendarId, ActionCommand $c, ?EventReference $ref): ActionResult
    {
        $this->client->delete($token, $calendarId, $ref->eventId, $c->expectedVersion);

        return ActionResult::success([$ref]);
    }
}
