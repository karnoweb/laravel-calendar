<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire\Concerns;

use Karnoweb\Calendar\Actions\ActionResultPresenter;
use Karnoweb\Calendar\Actions\CalendarMutationCoordinator;
use Karnoweb\Calendar\Actions\CalendarOperationId;
use Karnoweb\Calendar\Calendar\CalendarEngine;
use Karnoweb\Calendar\Calendar\IsoDate;
use Karnoweb\Calendar\Calendar\TimezoneConverter;
use Karnoweb\Calendar\Contracts\CalendarFormResolver;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Enums\RecurrenceScope;
use Karnoweb\Calendar\Security\CalendarAuthorizationGuard;
use Karnoweb\Calendar\Security\CalendarInputNormalizer;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Support\ResolvedCalendar;

trait HandlesCalendarForms
{
    public function openCreate(?string $iso = null, ?string $sourceKey = null, ?int $minute = null): void
    {
        $this->guarded(function (ResolvedCalendar $r) use ($iso, $sourceKey, $minute): void {
            $this->toast = null;
            $guard = app(CalendarAuthorizationGuard::class);
            $writable = [];
            foreach ($r->definition->sourceKeys() as $key) {
                if ($guard->writeDenial($r->definition, $r->context, CalendarAction::Create, $key) === null) {
                    $writable[] = $key;
                }
            }
            if ($writable === []) {
                $this->notify('error', (string) trans('calendar::calendar.messages.forbidden'));

                return;
            }
            if ($sourceKey !== null && ! in_array($sourceKey, $writable, true)) {
                $this->notify('error', (string) trans('calendar::calendar.messages.read_only'));

                return;
            }

            $engine = app(CalendarEngine::class);
            $state = $this->navState($r);
            $date = CalendarInputNormalizer::tryGregorianDate($iso) ?? $state->date;
            $start = max(0, min($minute ?? 9 * 60, 1439));
            $end = min($start + 60, 1439);

            $this->resetForm();
            $this->formOpen = true;
            $this->formMode = 'create';
            $this->formSource = $sourceKey ?? $writable[0];
            $this->formOperationId = CalendarOperationId::generate();
            $this->form = $this->defaults($engine, $state, $date, $start, $date, $end);
            $this->applySchemaDefaults($r, $this->formSource);
        });
    }

    public function updatedFormSource(string $value): void
    {
        if (! $this->formOpen || $this->formMode !== 'create') {
            return;
        }
        $this->guarded(function (ResolvedCalendar $r) use ($value): void {
            $guard = app(CalendarAuthorizationGuard::class);
            $writable = [];
            foreach ($r->definition->sourceKeys() as $key) {
                if ($guard->writeDenial($r->definition, $r->context, CalendarAction::Create, $key) === null) {
                    $writable[] = $key;
                }
            }
            if (! in_array($value, $writable, true)) {
                $this->formSource = $writable[0] ?? '';

                return;
            }
            $this->applySchemaDefaults($r, $value);
        });
    }

    protected function applySchemaDefaults(ResolvedCalendar $r, string $sourceKey): void
    {
        if ($sourceKey === '' || ! $r->definition->hasSource($sourceKey)) {
            return;
        }
        $schema = app(CalendarFormResolver::class)->schema($r->definition, $r->context, $sourceKey, null);
        foreach ($schema->fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            if (! array_key_exists($name, $this->form)) {
                $this->form[$name] = $field['default'] ?? (($field['type'] ?? '') === 'checkbox' ? false : '');
            } elseif (isset($field['default']) && ($this->form[$name] === '' || $this->form[$name] === null)) {
                $this->form[$name] = $field['default'];
            }
        }
    }

    public function openEdit(string $ref): void
    {
        $this->guarded(function (ResolvedCalendar $r) use ($ref): void {
            $this->toast = null;
            $event = $this->loadEvent($r, $ref);
            if ($event === null || ! $this->mayAct($r, $event, CalendarAction::Update)) {
                return;
            }
            if ($event->isRecurring && $r->definition->settings->feature('recurrence')) {
                $this->scopePrompt = ['intent' => 'edit', 'ref' => $ref, 'payload' => [], 'version' => $event->version, 'operationId' => ''];

                return;
            }
            $this->openEditForm($r, $ref, null, $event->version);
        });
    }

    protected function openEditForm(ResolvedCalendar $r, string $ref, ?RecurrenceScope $scope, ?string $version): void
    {
        $event = $this->loadEvent($r, $ref);
        $action = $scope?->updateAction() ?? CalendarAction::Update;
        if ($event === null || ! $this->mayAct($r, $event, $action === CalendarAction::SeriesSplit ? CalendarAction::Update : $action)) {
            return;
        }
        $engine = app(CalendarEngine::class);
        $state = $this->navState($r);
        $tz = $r->context->timezone;
        $first = $event->firstDate($tz);
        $last = $event->lastDate($tz);

        $this->resetForm();
        $this->formOpen = true;
        $this->formMode = 'edit';
        $this->formRef = $ref;
        $this->formSource = $event->reference->sourceKey;
        $this->formScope = $scope?->value;
        $this->formVersion = $event->version;
        $this->formOperationId = CalendarOperationId::generate();
        $this->form = $this->defaults(
            $engine, $state, $first,
            $event->allDay ? 540 : (int) $event->startAt($tz)->format('G') * 60 + (int) $event->startAt($tz)->format('i'),
            $last,
            $event->allDay ? 600 : (int) $event->endAt($tz)->format('G') * 60 + (int) $event->endAt($tz)->format('i'),
        );
        $this->form['title'] = $event->title;
        $this->form['allDay'] = $event->allDay;
        $this->form['movable'] = $event->capabilities->canMove;
        $this->form['location'] = (string) $event->location;
        $this->form['description'] = (string) $event->description;
        $this->form['status'] = (string) ($event->status ?? '');
        $this->form['category'] = (string) ($event->category ?? '');
        $this->applySchemaDefaults($r, $this->formSource);
    }

    public function submitForm(): void
    {
        $this->guarded(function (ResolvedCalendar $r): void {
            $this->toast = null;
            $this->formErrors = [];
            if (! $this->formOpen || ! $r->definition->hasSource($this->formSource)) {
                return;
            }
            $resolver = app(CalendarFormResolver::class);
            $schema = $resolver->schema($r->definition, $r->context, $this->formSource, null);
            $state = $this->navState($r);
            $result = $resolver->validate($schema, $this->form, $r->context, $state->system);

            if (! $result->valid) {
                $presenter = app(ActionResultPresenter::class);
                $this->formErrors = $presenter->fieldErrors($result->errors);
                $this->detectAmbiguity($r, $result->values, $result->errors);
                $this->notify('error', (string) trans('calendar::calendar.messages.validation'));

                return;
            }

            $input = $result->input;
            $payload = $input->toPayload();
            $reference = $this->formMode === 'edit' && $this->formRef !== null ? EventReferenceCodec::tryDecode($this->formRef) : null;
            $scope = RecurrenceScope::tryFrom((string) $this->formScope);
            if ($this->formMode === 'edit' && $reference === null) {
                $this->notify('error', (string) trans('calendar::calendar.messages.not_found'));

                return;
            }
            $action = $this->formMode === 'create' ? CalendarAction::Create : ($scope?->updateAction() ?? CalendarAction::Update);
            if ($action === CalendarAction::SeriesSplit) {
                $payload['mode'] = 'update';
            }
            // On update, an untouched "no repeat" means "keep the existing rule", not "remove it".
            if ($this->formMode === 'edit' && $input->recurrence === null) {
                $payload['recurrence'] = null;
            }

            try {
                $command = new ActionCommand(
                    $this->formOperationId !== '' ? $this->formOperationId : CalendarOperationId::generate(),
                    $action, $reference, $payload, $this->formVersion, $this->formSource, $scope,
                );
            } catch (\InvalidArgumentException) {
                $this->notify('error', (string) trans('calendar::calendar.messages.validation'));

                return;
            }
            $outcome = app(CalendarMutationCoordinator::class)->run($r->definition, $r->context, $command);
            $this->applyOutcome($outcome, $command->operationId);

            if ($outcome->result->fieldErrors !== []) {
                $this->formErrors = $outcome->presentation['fieldErrors'];
            }
            if ($outcome->closeForm) {
                $this->resetForm();
            } elseif (! in_array($outcome->result->status, [ActionStatus::UnknownOutcome, ActionStatus::RetryableError, ActionStatus::RateLimited], true)) {
                // A definitively rejected attempt may be corrected and re-submitted as a NEW intent.
                $this->formOperationId = CalendarOperationId::generate();
            }
            // UNKNOWN_OUTCOME keeps the SAME operation id so a retry cannot double-apply on an idempotent host.
        });
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->formOpen = false;
        $this->formMode = 'create';
        $this->formRef = null;
        $this->formSource = '';
        $this->formScope = null;
        $this->formVersion = null;
        $this->formOperationId = '';
        $this->form = [];
        $this->formErrors = [];
        $this->formOffsets = [];
    }

    /** @return array<string, mixed> */
    protected function defaults(CalendarEngine $engine, $state, string $startIso, int $startMinute, string $endIso, int $endMinute): array
    {
        $system = $engine->system($state);
        $fmt = function (string $iso) use ($system): string {
            [$y, $m, $d] = $system->fromIso($iso);

            return sprintf('%04d/%02d/%02d', $y, $m, $d);
        };

        return [
            'title' => '', 'allDay' => false, 'movable' => true,
            'startDate' => $fmt($startIso), 'startTime' => sprintf('%02d:%02d', intdiv($startMinute, 60), $startMinute % 60),
            'endDate' => $fmt($endIso), 'endTime' => sprintf('%02d:%02d', intdiv($endMinute, 60), $endMinute % 60),
            'startOffset' => '', 'endOffset' => '',
            'location' => '', 'description' => '',
            'recurrenceFreq' => '', 'recurrenceInterval' => 1, 'recurrenceWeekdays' => [], 'recurrenceEnd' => 'never',
            'recurrenceCount' => 5, 'recurrenceUntil' => '',
        ];
    }

    /**
     * When a local time is ambiguous (DST fold), expose the valid offsets so the user makes the choice visibly.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $errors
     */
    private function detectAmbiguity(ResolvedCalendar $r, array $values, array $errors): void
    {
        $this->formOffsets = [];
        $converter = app(TimezoneConverter::class);
        $system = app(CalendarEngine::class)->system($this->navState($r));
        foreach (['startTime' => ['startDate', 'startTime', 'startOffset'], 'endTime' => ['endDate', 'endTime', 'endOffset']] as $errorKey => [$dateKey, $timeKey, $offsetKey]) {
            $err = $errors[$errorKey] ?? null;
            $err = is_array($err) ? ($err[0] ?? null) : $err;
            if ($err !== 'calendar::calendar.validation.dst_ambiguous') {
                continue;
            }
            try {
                $iso = $system->toIso(...CalendarInputNormalizer::parseDate((string) $values[$dateKey]));
                [$h, $i] = CalendarInputNormalizer::parseTime((string) $values[$timeKey]);
                $this->formOffsets[$offsetKey] = $converter->candidateOffsets(sprintf('%s %02d:%02d', $iso, $h, $i), $r->context->timezone);
            } catch (\Throwable) {
                // ignore: the field error is already shown
            }
        }
    }

    protected function isoPlus(string $iso, int $days): string
    {
        return IsoDate::addDays($iso, $days);
    }

    protected function eventOrNull(?CalendarEventData $e): ?CalendarEventData
    {
        return $e;
    }
}
