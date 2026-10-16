<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Calendar\Contracts\ActionHandler;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CreateEventInput;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\MoveEventInput;
use Karnoweb\Calendar\Data\RecurrenceExceptionData;
use Karnoweb\Calendar\Data\RecurrenceSeriesData;
use Karnoweb\Calendar\Data\ResizeEventInput;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Enums\RecurrenceScope;
use Karnoweb\Calendar\Recurrence\RecurrenceSeriesSplitter;
use Workbench\App\Models\DemoRecurringSeries;
use Workbench\App\Models\DemoTask;
use Workbench\App\Policies\DemoTaskPolicy;

/**
 * Host write side. Every mutation runs in ONE transaction (a series split writes both series or neither),
 * checks scope, host policy and the expected version, and bumps the version on success.
 */
final class DemoTaskHandler implements ActionHandler
{
    public function __construct(
        private readonly RecurrenceSeriesSplitter $splitter,
        private readonly DemoTaskPolicy $policy,
    ) {}

    public function execute(ActionCommand $command, CalendarContext $context): ActionResult
    {
        $source = $command->sourceKey ?? $command->reference?->sourceKey;
        if ($source === 'google') {
            // Host policy first, then the package's Google handler (it re-checks token, ids and ETag).
            return $this->policy->allows($context, $command->action, null)
                ? app(\Karnoweb\Calendar\Google\GoogleCalendarHandler::class)->execute($command, $context)
                : ActionResult::failure(ActionStatus::Forbidden);
        }
        if (! in_array($source, ['tasks', 'reservations'], true)) {
            return ActionResult::failure(ActionStatus::Unsupported);
        }
        if ($source === 'reservations' && ! in_array($command->action, [CalendarAction::Move, CalendarAction::Resize], true)) {
            return ActionResult::failure(ActionStatus::Unsupported);
        }
        if (! $this->policy->allows($context, $command->action, null)) {
            return ActionResult::failure(ActionStatus::Forbidden);
        }

        return DB::transaction(fn () => $this->run($command, $context, $source));
    }

    private function run(ActionCommand $command, CalendarContext $context, string $source): ActionResult
    {
        if ($command->action === CalendarAction::Create) {
            return $this->create(CreateEventInput::fromCommand($command, $context->timezone), $context);
        }

        $ref = $command->reference;
        if ($ref === null || ! ctype_digit($ref->eventId)) {
            return ActionResult::failure(ActionStatus::NotFound);
        }

        return $ref->occurrenceKey !== null || $this->isSeriesAction($command->action)
            ? $this->seriesOperation($command, $context, $ref)
            : $this->taskOperation($command, $context, $ref, $source);
    }

    private function isSeriesAction(CalendarAction $a): bool
    {
        return in_array($a, [CalendarAction::SeriesUpdate, CalendarAction::SeriesDelete, CalendarAction::OccurrenceUpdate, CalendarAction::OccurrenceDelete, CalendarAction::SeriesSplit], true);
    }

    // ---------------------------------------------------------------- create

    private function create(CreateEventInput $in, CalendarContext $context): ActionResult
    {
        if ($in->recurrence !== null) {
            $s = DemoRecurringSeries::create([
                'scope_key' => $context->scopeKey, 'owner_id' => $context->actorId,
                'title' => $in->title, 'description' => $in->description, 'location' => $in->location,
                'starts_at' => $this->utc($in->start), 'ends_at' => $this->utc($in->end),
                'all_day' => $in->allDay, 'movable' => $in->movable, 'timezone' => $in->timezone,
                'rule' => $in->recurrence->toArray(), 'exceptions' => [], 'version' => 1,
            ]);

            return ActionResult::success([new EventReference('tasks', (string) $s->id)], newVersion: '1');
        }

        $t = DemoTask::create([
            'scope_key' => $context->scopeKey, 'owner_id' => $context->actorId, 'kind' => 'task',
            'title' => $in->title, 'description' => $in->description, 'location' => $in->location,
            'status' => $in->status ?? 'open', 'timezone' => $in->timezone, 'all_day' => $in->allDay, 'movable' => $in->movable, 'version' => 1,
        ] + $this->times($in->start, $in->end, $in->allDay));

        return ActionResult::success([new EventReference('tasks', (string) $t->id)], newVersion: '1');
    }

    // ---------------------------------------------------------------- plain tasks / reservations

    private function taskOperation(ActionCommand $command, CalendarContext $context, EventReference $ref, string $source): ActionResult
    {
        $kind = $source === 'reservations' ? 'reservation' : 'task';
        $task = DemoTask::query()->where('scope_key', $context->scopeKey)->where('kind', $kind)->lockForUpdate()->find((int) $ref->eventId);
        if ($task === null) {
            return ActionResult::failure(ActionStatus::NotFound);
        }
        if (! $this->policy->allows($context, $command->action, $task)) {
            return ActionResult::failure(ActionStatus::Forbidden);
        }
        if ($command->expectedVersion !== null && $command->expectedVersion !== (string) $task->version) {
            return ActionResult::failure(ActionStatus::Conflict);
        }

        switch ($command->action) {
            case CalendarAction::Delete:
                $task->delete();

                return ActionResult::success([$ref]);
            case CalendarAction::Update:
                $in = CreateEventInput::fromCommand($command, $context->timezone);
                if ($in->recurrence !== null) {
                    return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.unsupported');
                }
                $task->fill(['title' => $in->title, 'description' => $in->description, 'location' => $in->location, 'timezone' => $in->timezone, 'all_day' => $in->allDay, 'movable' => $in->movable]
                    + $this->times($in->start, $in->end, $in->allDay));
                break;
            case CalendarAction::Move:
                if (! ($task->movable ?? true)) {
                    return ActionResult::failure(ActionStatus::ReadOnly, 'calendar::calendar.messages.read_only');
                }
                $m = MoveEventInput::fromCommand($command, $context->timezone);
                $task->fill($this->times($m->start, $m->end, $m->allDay));
                break;
            case CalendarAction::Resize:
                if (! ($task->movable ?? true)) {
                    return ActionResult::failure(ActionStatus::ReadOnly, 'calendar::calendar.messages.read_only');
                }
                $r = ResizeEventInput::fromCommand($command, $context->timezone);
                $task->ends_at = $this->utc($r->end);
                break;
            default:
                return ActionResult::failure(ActionStatus::Unsupported);
        }
        $task->version++;
        $task->save();

        return ActionResult::success([$ref], newVersion: (string) $task->version);
    }

    // ---------------------------------------------------------------- recurring series

    private function seriesOperation(ActionCommand $command, CalendarContext $context, EventReference $ref): ActionResult
    {
        $row = DemoRecurringSeries::query()->where('scope_key', $context->scopeKey)->lockForUpdate()->find((int) $ref->eventId);
        if ($row === null) {
            return ActionResult::failure(ActionStatus::NotFound);
        }
        if ($command->expectedVersion !== null && $command->expectedVersion !== (string) $row->version) {
            return ActionResult::failure(ActionStatus::Conflict);
        }
        $series = $row->toSeriesData();
        $key = $ref->occurrenceKey;
        $scope = $command->scope ?? match ($command->action) {
            CalendarAction::OccurrenceUpdate, CalendarAction::OccurrenceDelete => RecurrenceScope::This,
            CalendarAction::SeriesSplit => RecurrenceScope::Future,
            default => RecurrenceScope::All,
        };

        switch ($command->action) {
            case CalendarAction::SeriesDelete:
                $row->delete();

                return ActionResult::success([$ref->withoutOccurrence()]);
            case CalendarAction::OccurrenceDelete:
                return $this->cancelOccurrence($row, $series, (string) $key);
            case CalendarAction::OccurrenceUpdate:
                $in = CreateEventInput::fromCommand($command, $context->timezone);

                return $this->replaceOccurrence($row, $series, (string) $key, $in->start, $in->end, ['title' => $in->title, 'description' => $in->description, 'location' => $in->location]);
            case CalendarAction::SeriesUpdate:
                $in = CreateEventInput::fromCommand($command, $context->timezone);

                return $this->updateWholeSeries($row, $series, $key, $in);
            case CalendarAction::SeriesSplit:
                if (($command->payload['mode'] ?? 'update') === 'delete') {
                    return $this->truncate($row, $series, (string) $key);
                }
                $in = CreateEventInput::fromCommand($command, $context->timezone);

                return $this->splitSeries($row, $series, (string) $key, $in->start, $in->end, $in);
            case CalendarAction::Move:
                if (! ($row->movable ?? true)) {
                    return ActionResult::failure(ActionStatus::ReadOnly, 'calendar::calendar.messages.read_only');
                }
                $m = MoveEventInput::fromCommand($command, $context->timezone);

                return $this->moveOrResize($row, $series, (string) $key, $scope, $m->start, $m->end);
            case CalendarAction::Resize:
                if (! ($row->movable ?? true)) {
                    return ActionResult::failure(ActionStatus::ReadOnly, 'calendar::calendar.messages.read_only');
                }
                $r = ResizeEventInput::fromCommand($command, $context->timezone);
                [$curStart] = $this->currentTimes($series, (string) $key);

                return $this->moveOrResize($row, $series, (string) $key, $scope, $curStart, $r->end);
            default:
                return ActionResult::failure(ActionStatus::Unsupported);
        }
    }

    private function moveOrResize(DemoRecurringSeries $row, RecurrenceSeriesData $series, string $key, RecurrenceScope $scope, DateTimeImmutable $start, DateTimeImmutable $end): ActionResult
    {
        return match ($scope) {
            RecurrenceScope::This => $this->replaceOccurrence($row, $series, $key, $start, $end, []),
            RecurrenceScope::Future => $this->splitSeries($row, $series, $key, $start, $end, null),
            RecurrenceScope::All => $this->shiftSeries($row, $series, $key, $start, $end),
        };
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} current (possibly replaced) times of one occurrence */
    private function currentTimes(RecurrenceSeriesData $series, string $key): array
    {
        foreach ($series->exceptions as $ex) {
            if ($ex->originalOccurrenceKey === $key && ! $ex->isCancelled()) {
                return [new DateTimeImmutable((string) $ex->replacement['start']), new DateTimeImmutable((string) $ex->replacement['end'])];
            }
        }
        $original = $this->original($series, $key);

        return [$original, $original->modify('+'.($series->end->getTimestamp() - $series->start->getTimestamp()).' seconds')];
    }

    private function original(RecurrenceSeriesData $series, string $key): DateTimeImmutable
    {
        $format = $series->allDay ? '!Y-m-d' : '!Y-m-d\TH:i:s';
        $d = DateTimeImmutable::createFromFormat($format, $key, new DateTimeZone($series->timezone));
        if ($d === false) {
            throw new InvalidArgumentException('Invalid occurrence key.');
        }

        return $d;
    }

    /** @param list<RecurrenceExceptionData> $exceptions */
    private function saveExceptions(DemoRecurringSeries $row, array $exceptions): ActionResult
    {
        $row->exceptions = DemoRecurringSeries::exceptionsToArray($exceptions);
        $row->version++;
        $row->save();

        return ActionResult::success(newVersion: (string) $row->version);
    }

    private function withException(RecurrenceSeriesData $series, RecurrenceExceptionData $new): array
    {
        $out = array_values(array_filter($series->exceptions, fn (RecurrenceExceptionData $e) => $e->originalOccurrenceKey !== $new->originalOccurrenceKey));
        $out[] = $new;

        return $out;
    }

    private function cancelOccurrence(DemoRecurringSeries $row, RecurrenceSeriesData $series, string $key): ActionResult
    {
        return $this->saveExceptions($row, $this->withException($series, new RecurrenceExceptionData($key, RecurrenceExceptionData::CANCELLED)));
    }

    /** @param array<string, mixed> $extra */
    private function replaceOccurrence(DemoRecurringSeries $row, RecurrenceSeriesData $series, string $key, DateTimeImmutable $start, DateTimeImmutable $end, array $extra): ActionResult
    {
        $replacement = ['start' => $start->format('c'), 'end' => $end->format('c')] + array_filter($extra, fn ($v) => $v !== null);

        return $this->saveExceptions($row, $this->withException($series, new RecurrenceExceptionData($key, RecurrenceExceptionData::REPLACED, $replacement)));
    }

    private function shiftSeries(DemoRecurringSeries $row, RecurrenceSeriesData $series, string $key, DateTimeImmutable $newStart, DateTimeImmutable $newEnd): ActionResult
    {
        [$curStart] = $this->currentTimes($series, $key);
        $startDelta = $newStart->getTimestamp() - $curStart->getTimestamp();
        $row->starts_at = $this->utc($series->start->modify(($startDelta >= 0 ? '+' : '').$startDelta.' seconds'));
        $row->ends_at = $this->utc($series->start->modify(($startDelta >= 0 ? '+' : '').$startDelta.' seconds')->modify('+'.($newEnd->getTimestamp() - $newStart->getTimestamp()).' seconds'));
        $row->version++;
        $row->save();

        return ActionResult::success(newVersion: (string) $row->version);
    }

    private function updateWholeSeries(DemoRecurringSeries $row, RecurrenceSeriesData $series, ?string $key, CreateEventInput $in): ActionResult
    {
        $row->fill(['title' => $in->title, 'description' => $in->description, 'location' => $in->location, 'movable' => $in->movable]);
        if ($key !== null) {
            [$curStart] = $this->currentTimes($series, $key);
            $delta = $in->start->getTimestamp() - $curStart->getTimestamp();
            $startAt = $series->start->modify(($delta >= 0 ? '+' : '').$delta.' seconds');
            $row->starts_at = $this->utc($startAt);
            $row->ends_at = $this->utc($startAt->modify('+'.($in->end->getTimestamp() - $in->start->getTimestamp()).' seconds'));
        }
        if ($in->recurrence !== null) {
            $row->rule = $in->recurrence->toArray();
        }
        $row->version++;
        $row->save();

        return ActionResult::success(newVersion: (string) $row->version);
    }

    private function truncate(DemoRecurringSeries $row, RecurrenceSeriesData $series, string $key): ActionResult
    {
        try {
            $parts = $this->splitter->split($series, $key, '0');
        } catch (InvalidArgumentException) {
            $row->delete(); // "this and following" from the first occurrence is the whole series

            return ActionResult::success();
        }
        $this->persistBefore($row, $parts['before']);

        return ActionResult::success(newVersion: (string) $row->version);
    }

    private function splitSeries(DemoRecurringSeries $row, RecurrenceSeriesData $series, string $key, DateTimeImmutable $newStart, DateTimeImmutable $newEnd, ?CreateEventInput $in): ActionResult
    {
        try {
            $parts = $this->splitter->split($series, $key, '0', $newStart, $newEnd);
        } catch (InvalidArgumentException) {
            // First occurrence: a split is a whole-series change.
            return $in === null ? $this->shiftSeries($row, $series, $key, $newStart, $newEnd) : $this->updateWholeSeries($row, $series, $key, $in);
        }

        $after = $parts['after'];
        $new = DemoRecurringSeries::create([
            'scope_key' => $row->scope_key, 'owner_id' => $row->owner_id,
            'title' => $in?->title ?? $row->title, 'description' => $in !== null ? $in->description : $row->description,
            'location' => $in !== null ? $in->location : $row->location,
            'starts_at' => $this->utc($after->start), 'ends_at' => $this->utc($after->end),
            'all_day' => $row->all_day, 'timezone' => $row->timezone,
            'rule' => ($in?->recurrence ?? $after->rule)->toArray(),
            'exceptions' => DemoRecurringSeries::exceptionsToArray($after->exceptions), 'version' => 1,
        ]);
        $this->persistBefore($row, $parts['before']);

        return ActionResult::success([new EventReference('tasks', (string) $new->id)], newVersion: (string) $row->version);
    }

    private function persistBefore(DemoRecurringSeries $row, RecurrenceSeriesData $before): void
    {
        $row->rule = $before->rule->toArray();
        $row->exceptions = DemoRecurringSeries::exceptionsToArray($before->exceptions);
        $row->version++;
        $row->save();
    }

    // ---------------------------------------------------------------- time helpers

    private function utc(DateTimeImmutable $d): string
    {
        return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** @return array{starts_at: string, ends_at: string} all-day => floating dates at 00:00, timed => UTC instants */
    private function times(DateTimeImmutable $start, DateTimeImmutable $end, bool $allDay): array
    {
        if ($allDay) {
            return ['starts_at' => $start->format('Y-m-d').' 00:00:00', 'ends_at' => $end->format('Y-m-d').' 00:00:00'];
        }

        return ['starts_at' => $this->utc($start), 'ends_at' => $this->utc($end)];
    }
}
