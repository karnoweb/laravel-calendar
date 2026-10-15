<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire\Concerns;

use InvalidArgumentException;
use Karnoweb\Calendar\Actions\CalendarConfirmationPolicy;
use Karnoweb\Calendar\Actions\CalendarMutationCoordinator;
use Karnoweb\Calendar\Actions\CalendarOperationId;
use Karnoweb\Calendar\Actions\MutationOutcome;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Enums\RecurrenceScope;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Exceptions\CalendarNotFound;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;
use Karnoweb\Calendar\Forms\FormValidationMapper;
use Karnoweb\Calendar\Security\CalendarAuthorizationGuard;
use Karnoweb\Calendar\Security\CalendarInputNormalizer;
use Karnoweb\Calendar\Security\EventReferenceGuard;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Support\ResolvedCalendar;
use Karnoweb\Calendar\Support\TimeMovePolicy;

/**
 * Every public method here is browser-callable, so each one:
 *  1. re-resolves definition + context (server truth),
 *  2. re-fetches the target event through the authorised provider,
 *  3. hands the command to the ActionDispatcher (never straight to a handler).
 * The UI only ever shows success for ActionStatus::Success.
 */
trait HandlesCalendarActions
{
    public function openDay(string $iso): void
    {
        $date = CalendarInputNormalizer::tryGregorianDate($iso);
        if ($date === null) {
            return;
        }
        $this->guarded(function () use ($date): void {
            $this->drawerDate = $date;
            $this->drawerPages = 1;
        });
    }

    public function closeDay(): void
    {
        $this->drawerDate = null;
        $this->drawerPages = 1;
    }

    public function loadMore(): void
    {
        $this->guarded(function (ResolvedCalendar $r): void {
            $this->pages = min($this->pages + 1, $r->definition->settings->limit('maxPages'));
        });
    }

    public function loadMoreDay(): void
    {
        $this->guarded(function (ResolvedCalendar $r): void {
            $this->drawerPages = min($this->drawerPages + 1, $r->definition->settings->limit('maxPages'));
        });
    }

    public function openEvent(string $ref): void
    {
        if (EventReferenceCodec::tryDecode($ref) === null) {
            return;
        }
        $this->guarded(function () use ($ref): void {
            $this->detailsRef = $ref; // verified (authorised re-fetch) while rendering
            $this->toast = null;
        });
    }

    public function closeDetails(): void
    {
        $this->detailsRef = null;
    }

    public function requestMove(string $ref, string $targetIso, ?int $minute = null, string $operationId = '', ?string $version = null): void
    {
        $this->guarded(function (ResolvedCalendar $r) use ($ref, $targetIso, $minute, $operationId, $version): void {
            $this->toast = null;
            $iso = CalendarInputNormalizer::tryGregorianDate($targetIso);
            $event = $iso === null ? null : $this->loadEvent($r, $ref);
            if ($event === null || $iso === null || ! $this->mayAct($r, $event, CalendarAction::Move)) {
                return;
            }
            $tz = $r->context->timezone;
            try {
                $times = $minute === null
                    ? app(TimeMovePolicy::class)->moveToDate($event, $iso, $tz)
                    : app(TimeMovePolicy::class)->moveToTime($event, $iso, $minute, $tz);
            } catch (InvalidCalendarDate $e) {
                $this->notify('error', (string) trans(FormValidationMapper::dateError($e)));
                $this->dispatch('kw-calendar:result', status: 'INVALID', operationId: $operationId);

                return;
            }
            $payload = [
                'mode' => $minute === null ? 'date' : 'time',
                'start' => $event->allDay ? $times['start']->format('Y-m-d') : $times['start']->format('c'),
                'end' => $event->allDay ? $times['end']->format('Y-m-d') : $times['end']->format('c'),
                'allDay' => $event->allDay,
                'timezone' => $tz,
            ];
            $this->begin($r, $event, 'move', CalendarAction::Move, $ref, $payload, $version, $operationId);
        });
    }

    public function requestResize(string $ref, string $targetIso, int $endMinute, string $operationId = '', ?string $version = null): void
    {
        $this->guarded(function (ResolvedCalendar $r) use ($ref, $targetIso, $endMinute, $operationId, $version): void {
            $this->toast = null;
            $iso = CalendarInputNormalizer::tryGregorianDate($targetIso);
            $event = $iso === null ? null : $this->loadEvent($r, $ref);
            if ($event === null || $iso === null || ! $this->mayAct($r, $event, CalendarAction::Resize)) {
                return;
            }
            try {
                $end = app(TimeMovePolicy::class)->resizeEnd($event, $iso, $endMinute, $r->context->timezone);
            } catch (InvalidCalendarDate $e) {
                $this->notify('error', (string) trans(FormValidationMapper::dateError($e)));
                $this->dispatch('kw-calendar:result', status: 'INVALID', operationId: $operationId);

                return;
            }
            $payload = ['end' => $end->format('c'), 'timezone' => $r->context->timezone];
            $this->begin($r, $event, 'resize', CalendarAction::Resize, $ref, $payload, $version, $operationId);
        });
    }

    public function requestDelete(string $ref, ?string $version = null): void
    {
        $this->guarded(function (ResolvedCalendar $r) use ($ref, $version): void {
            $this->toast = null;
            $event = $this->loadEvent($r, $ref);
            if ($event === null || ! $this->mayAct($r, $event, CalendarAction::Delete)) {
                return;
            }
            $this->begin($r, $event, 'delete', CalendarAction::Delete, $ref, [], $version, '');
        });
    }

    public function confirmPending(): void
    {
        $this->guarded(function (ResolvedCalendar $r): void {
            $pending = $this->pending;
            $this->pending = null;
            if ($pending !== null) {
                $this->runPending($r, $pending);
            }
        });
    }

    /** Cancelling NEVER reaches the handler; the UI returns to the last authoritative state. */
    public function cancelPending(): void
    {
        $operationId = (string) ($this->pending['operationId'] ?? '');
        $this->pending = null;
        $this->scopePrompt = null;
        $this->notify('info', (string) trans('calendar::calendar.messages.cancelled'));
        $this->dispatch('kw-calendar:result', status: 'CANCELLED', operationId: $operationId);
    }

    // ------------------------------------------------------------------ internals

    /** Fetch the event through the authorised provider; on any failure tell the user and refetch. */
    protected function loadEvent(ResolvedCalendar $r, string $ref): ?CalendarEventData
    {
        $reference = EventReferenceCodec::tryDecode($ref);
        if ($reference === null) {
            $this->notify('error', (string) trans('calendar::calendar.messages.not_found'));

            return null;
        }
        try {
            return app(EventReferenceGuard::class)->resolve($r->definition, $r->context, $reference);
        } catch (CalendarAccessDenied) {
            $this->notify('error', (string) trans('calendar::calendar.messages.forbidden'));
        } catch (CalendarNotFound) {
            $this->notify('error', (string) trans('calendar::calendar.messages.not_found'));
        }
        $this->dispatch('kw-calendar:result', status: 'NOT_FOUND', operationId: '');

        return null;
    }

    /** Early, user-friendly gate. The dispatcher enforces the same rules again on the real call. */
    protected function mayAct(ResolvedCalendar $r, CalendarEventData $event, CalendarAction $action): bool
    {
        $denial = app(CalendarAuthorizationGuard::class)->writeDenial($r->definition, $r->context, $action, $event->reference->sourceKey);
        if ($denial === null && ! $event->capabilities->allows($action->capability())) {
            $denial = ActionStatus::ReadOnly;
        }
        if ($denial === null) {
            return true;
        }
        $this->notify('error', (string) trans('calendar::calendar.messages.'.strtolower($denial->value)));
        $this->dispatch('kw-calendar:result', status: $denial->value, operationId: '');

        return false;
    }

    /**
     * Start an operation: recurring events first ask for a scope; then confirmation (if required); then run.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function begin(ResolvedCalendar $r, CalendarEventData $event, string $intent, CalendarAction $action, string $ref, array $payload, ?string $version, string $operationId): void
    {
        $operationId = CalendarOperationId::isValid($operationId) ? $operationId : CalendarOperationId::generate();

        if ($event->isRecurring && $r->definition->settings->feature('recurrence')) {
            $this->scopePrompt = [
                'intent' => $intent, 'ref' => $ref, 'payload' => $payload, 'version' => $version,
                'operationId' => $operationId,
            ];

            return;
        }
        $this->stage($r, $action, $ref, $payload, $version, $operationId, null);
    }

    /** @param array<string, mixed> $payload */
    protected function stage(ResolvedCalendar $r, CalendarAction $action, ?string $ref, array $payload, ?string $version, string $operationId, ?RecurrenceScope $scope): void
    {
        $pending = [
            'action' => $action->value, 'ref' => $ref, 'payload' => $payload, 'version' => $version,
            'operationId' => $operationId, 'scope' => $scope?->value,
            'kind' => match ($action) {
                CalendarAction::Move => 'move',
                CalendarAction::Resize => 'resize',
                default => 'delete',
            },
        ];
        if (app(CalendarConfirmationPolicy::class)->requiresConfirmation($r->definition, $action)) {
            $this->pending = $pending;

            return;
        }
        $this->runPending($r, $pending);
    }

    /** @param array<string, mixed> $p */
    protected function runPending(ResolvedCalendar $r, array $p): void
    {
        $action = CalendarAction::tryFrom((string) ($p['action'] ?? ''));
        $reference = isset($p['ref']) && is_string($p['ref']) ? EventReferenceCodec::tryDecode($p['ref']) : null;
        if ($action === null || ($action->requiresReference() && $reference === null)) {
            $this->notify('error', (string) trans('calendar::calendar.messages.validation'));

            return;
        }
        try {
            $command = new ActionCommand(
                (string) ($p['operationId'] ?? ''),
                $action,
                $reference,
                is_array($p['payload'] ?? null) ? $p['payload'] : [],
                isset($p['version']) && is_string($p['version']) ? $p['version'] : null,
                $reference?->sourceKey,
                RecurrenceScope::tryFrom((string) ($p['scope'] ?? '')),
            );
        } catch (InvalidArgumentException) {
            $this->notify('error', (string) trans('calendar::calendar.messages.validation'));

            return;
        }
        $this->applyOutcome(app(CalendarMutationCoordinator::class)->run($r->definition, $r->context, $command), $command->operationId);
    }

    protected function applyOutcome(MutationOutcome $outcome, string $operationId): void
    {
        $this->toast = ['type' => $outcome->presentation['type'], 'message' => $outcome->presentation['message']];
        $status = $outcome->result->status;
        $this->outcomeState = match ($status) {
            ActionStatus::Conflict => 'conflict',
            ActionStatus::UnknownOutcome => 'unknown',
            default => '',
        };
        if ($outcome->isSuccess()) {
            $this->scopePrompt = null;
            $this->pending = null;
            $this->detailsRef = null;
        }
        $this->dispatch('kw-calendar:result', status: $status->value, operationId: $operationId);
    }
}
