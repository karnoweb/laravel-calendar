<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Providers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\CalendarModelDefinition;
use Karnoweb\Calendar\Data\CreateEventInput;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\MoveEventInput;
use Karnoweb\Calendar\Data\OccurrenceData;
use Karnoweb\Calendar\Data\RecurrenceExceptionData;
use Karnoweb\Calendar\Data\RecurrenceRuleData;
use Karnoweb\Calendar\Data\RecurrenceSeriesData;
use Karnoweb\Calendar\Data\ResizeEventInput;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Enums\RecurrenceScope;
use Karnoweb\Calendar\Recurrence\RecurrenceExpander;
use Karnoweb\Calendar\Recurrence\RecurrenceSeriesSplitter;

/**
 * Persists recurrence on the host row (rule + exceptions) and expands occurrences in memory.
 * SQL stays inside the caller scope; expansion never adds rows from another scope.
 */
final class EloquentSeries
{
    public function __construct(
        private readonly RecurrenceExpander $expander = new RecurrenceExpander,
        private readonly RecurrenceSeriesSplitter $splitter = new RecurrenceSeriesSplitter,
    ) {}

    public function hasRule(Model $record, CalendarModelDefinition $def): bool
    {
        if (! $def->persistsRecurrence()) {
            return false;
        }
        $rule = $record->getAttribute((string) $def->recurrenceRuleField);

        return is_array($rule) && $rule !== [];
    }

    /** @return list<CalendarEventData> */
    public function expand(Model $record, CalendarModelDefinition $def, string $sourceKey, CalendarContext $context, EventQuery $query, EloquentEventProvider $provider): array
    {
        $series = $this->hydrate($record, $def, $context);
        if ($series === null) {
            return [$provider->toEvent($record, $def, $sourceKey, $context)];
        }

        $events = [];
        foreach ($this->expander->expand($series, $sourceKey, (string) $record->getKey(), $query->range) as $occurrence) {
            $events[] = $provider->toEvent($record, $def, $sourceKey, $context, $occurrence, $this->presentation($series, $occurrence->key()));
        }

        return $events;
    }

    public function findOne(Model $record, CalendarModelDefinition $def, string $sourceKey, CalendarContext $context, string $occurrenceKey, EloquentEventProvider $provider): ?CalendarEventData
    {
        $series = $this->hydrate($record, $def, $context);
        if ($series === null) {
            return null;
        }

        foreach ($series->exceptions as $exception) {
            if ($exception->originalOccurrenceKey !== $occurrenceKey) {
                continue;
            }
            if ($exception->isCancelled() || ! is_array($exception->replacement)) {
                return null;
            }
            $occurrence = $this->occurrenceFromReplacement($series, $sourceKey, (string) $record->getKey(), $exception);
            if ($occurrence === null) {
                return null;
            }

            return $provider->toEvent($record, $def, $sourceKey, $context, $occurrence, $this->presentation($series, $occurrenceKey));
        }

        $original = $this->original($series, $occurrenceKey);
        $range = new DateRange($original->modify('-1 day'), $original->modify('+2 days'), $series->timezone);
        foreach ($this->expander->expand($series, $sourceKey, (string) $record->getKey(), $range) as $occurrence) {
            if ($occurrence->key() === $occurrenceKey) {
                return $provider->toEvent($record, $def, $sourceKey, $context, $occurrence, $this->presentation($series, $occurrenceKey));
            }
        }

        return null;
    }

    public function mutate(ActionCommand $command, CalendarContext $context, string $sourceKey, CalendarModelDefinition $def, EloquentEventProvider $provider, Model $record): ActionResult
    {
        $series = $this->hydrate($record, $def, $context);
        if ($series === null) {
            return ActionResult::failure(ActionStatus::NotFound, 'calendar::calendar.messages.not_found');
        }

        if ($command->reference === null) {
            return ActionResult::failure(ActionStatus::NotFound, 'calendar::calendar.messages.not_found');
        }
        $key = $command->reference->occurrenceKey;
        $scope = $command->scope ?? match ($command->action) {
            CalendarAction::OccurrenceUpdate, CalendarAction::OccurrenceDelete => RecurrenceScope::This,
            CalendarAction::SeriesSplit => RecurrenceScope::Future,
            default => RecurrenceScope::All,
        };

        switch ($command->action) {
            case CalendarAction::SeriesDelete:
                $record->delete();

                return ActionResult::success([$command->reference->withoutOccurrence()], newVersion: $provider->extractVersion($record, $def));
            case CalendarAction::OccurrenceDelete:
                return $this->cancel($record, $def, $series, (string) $key, $provider);
            case CalendarAction::OccurrenceUpdate:
                $in = CreateEventInput::fromCommand($command, $context->timezone);

                return $this->replace($record, $def, $series, (string) $key, $in->start, $in->end, [
                    'title' => $in->title,
                    'description' => $in->description,
                    'location' => $in->location,
                ], $provider);
            case CalendarAction::SeriesUpdate:
                return $this->updateAll($record, $def, $series, $key, CreateEventInput::fromCommand($command, $context->timezone), $provider);
            case CalendarAction::SeriesSplit:
                if (($command->payload['mode'] ?? 'update') === 'delete') {
                    return $this->truncate($record, $def, $series, (string) $key, $provider);
                }
                $in = CreateEventInput::fromCommand($command, $context->timezone);

                return $this->split($record, $def, $series, $sourceKey, (string) $key, $in->start, $in->end, $in, $provider);
            case CalendarAction::Move:
                $m = MoveEventInput::fromCommand($command, $context->timezone);

                return $this->moveOrResize($record, $def, $series, $sourceKey, (string) $key, $scope, $m->start, $m->end, $provider);
            case CalendarAction::Resize:
                $r = ResizeEventInput::fromCommand($command, $context->timezone);
                [$currentStart] = $this->currentTimes($series, (string) $key);

                return $this->moveOrResize($record, $def, $series, $sourceKey, (string) $key, $scope, $currentStart, $r->end, $provider);
            default:
                return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.unsupported');
        }
    }

    public function storeRule(Model $record, CalendarModelDefinition $def, RecurrenceRuleData $rule): void
    {
        $record->setAttribute((string) $def->recurrenceRuleField, $rule->toArray());
        if ($record->getAttribute((string) $def->recurrenceExceptionsField) === null) {
            $record->setAttribute((string) $def->recurrenceExceptionsField, []);
        }
    }

    private function moveOrResize(Model $record, CalendarModelDefinition $def, RecurrenceSeriesData $series, string $sourceKey, string $key, RecurrenceScope $scope, DateTimeImmutable $start, DateTimeImmutable $end, EloquentEventProvider $provider): ActionResult
    {
        return match ($scope) {
            RecurrenceScope::This => $this->replace($record, $def, $series, $key, $start, $end, [], $provider),
            RecurrenceScope::Future => $this->split($record, $def, $series, $sourceKey, $key, $start, $end, null, $provider),
            RecurrenceScope::All => $this->shift($record, $def, $series, $key, $start, $end, $provider),
        };
    }

    /** @param array<string, mixed> $extra */
    private function replace(Model $record, CalendarModelDefinition $def, RecurrenceSeriesData $series, string $key, DateTimeImmutable $start, DateTimeImmutable $end, array $extra, EloquentEventProvider $provider): ActionResult
    {
        $replacement = ['start' => $start->format('c'), 'end' => $end->format('c')];
        foreach ($extra as $name => $value) {
            if ($value !== null) {
                $replacement[$name] = $value;
            }
        }

        return $this->saveExceptions($record, $def, $this->withException($series, new RecurrenceExceptionData($key, RecurrenceExceptionData::REPLACED, $replacement)), $provider);
    }

    private function cancel(Model $record, CalendarModelDefinition $def, RecurrenceSeriesData $series, string $key, EloquentEventProvider $provider): ActionResult
    {
        return $this->saveExceptions($record, $def, $this->withException($series, new RecurrenceExceptionData($key, RecurrenceExceptionData::CANCELLED)), $provider);
    }

    private function updateAll(Model $record, CalendarModelDefinition $def, RecurrenceSeriesData $series, ?string $key, CreateEventInput $in, EloquentEventProvider $provider): ActionResult
    {
        $titleCol = $def->titleFieldName();
        $record->setAttribute($titleCol, $in->title);
        if ($def->descriptionField !== null) {
            $record->setAttribute($def->descriptionField, $in->description);
        }
        if ($def->locationField !== null) {
            $record->setAttribute($def->locationField, $in->location);
        }
        if ($key !== null) {
            $this->shiftTimes($record, $def, $series, $key, $in->start, $in->end);
        }
        if ($in->recurrence !== null) {
            $this->storeRule($record, $def, $in->recurrence);
        }
        $this->bump($record, $def);
        $record->save();

        return ActionResult::success(newVersion: $provider->extractVersion($record, $def));
    }

    private function shift(Model $record, CalendarModelDefinition $def, RecurrenceSeriesData $series, string $key, DateTimeImmutable $start, DateTimeImmutable $end, EloquentEventProvider $provider): ActionResult
    {
        $this->shiftTimes($record, $def, $series, $key, $start, $end);
        $this->bump($record, $def);
        $record->save();

        return ActionResult::success(newVersion: $provider->extractVersion($record, $def));
    }

    private function shiftTimes(Model $record, CalendarModelDefinition $def, RecurrenceSeriesData $series, string $key, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        [$currentStart] = $this->currentTimes($series, $key);
        $delta = $start->getTimestamp() - $currentStart->getTimestamp();
        $seriesStart = $series->start->modify(($delta >= 0 ? '+' : '').$delta.' seconds');
        $allDay = $this->allDay($record, $def);
        $record->setAttribute($def->startField, $this->format($seriesStart, $allDay));
        if ($def->endField !== null) {
            $record->setAttribute($def->endField, $this->format($seriesStart->modify('+'.($end->getTimestamp() - $start->getTimestamp()).' seconds'), $allDay));
        }
    }

    private function truncate(Model $record, CalendarModelDefinition $def, RecurrenceSeriesData $series, string $key, EloquentEventProvider $provider): ActionResult
    {
        try {
            $parts = $this->splitter->split($series, $key, 'pending');
        } catch (InvalidArgumentException) {
            $record->delete();

            return ActionResult::success();
        }
        $this->persistBefore($record, $def, $parts['before']);

        return ActionResult::success(newVersion: $provider->extractVersion($record, $def));
    }

    private function split(Model $record, CalendarModelDefinition $def, RecurrenceSeriesData $series, string $sourceKey, string $key, DateTimeImmutable $start, DateTimeImmutable $end, ?CreateEventInput $in, EloquentEventProvider $provider): ActionResult
    {
        try {
            $parts = $this->splitter->split($series, $key, 'pending', $start, $end);
        } catch (InvalidArgumentException) {
            return $in === null
                ? $this->shift($record, $def, $series, $key, $start, $end, $provider)
                : $this->updateAll($record, $def, $series, $key, $in, $provider);
        }

        $after = $parts['after'];
        $copy = $record->replicate();
        $allDay = $this->allDay($record, $def);
        $copy->setAttribute($def->startField, $this->format($after->start, $allDay));
        if ($def->endField !== null) {
            $copy->setAttribute($def->endField, $this->format($after->end, $allDay));
        }
        $rule = $in?->recurrence ?? $after->rule;
        $copy->setAttribute((string) $def->recurrenceRuleField, $rule->toArray());
        $copy->setAttribute((string) $def->recurrenceExceptionsField, $this->exceptionsToArray($after->exceptions));
        if ($def->versionField !== null) {
            $copy->setAttribute($def->versionField, 1);
        }
        if ($in !== null) {
            $copy->setAttribute($def->titleFieldName(), $in->title);
            if ($def->descriptionField !== null) {
                $copy->setAttribute($def->descriptionField, $in->description);
            }
            if ($def->locationField !== null) {
                $copy->setAttribute($def->locationField, $in->location);
            }
        }
        $copy->save();
        $this->persistBefore($record, $def, $parts['before']);

        return ActionResult::success(
            [new EventReference($sourceKey, (string) $copy->getKey())],
            newVersion: $provider->extractVersion($record, $def),
        );
    }

    private function persistBefore(Model $record, CalendarModelDefinition $def, RecurrenceSeriesData $before): void
    {
        $record->setAttribute((string) $def->recurrenceRuleField, $before->rule->toArray());
        $record->setAttribute((string) $def->recurrenceExceptionsField, $this->exceptionsToArray($before->exceptions));
        $this->bump($record, $def);
        $record->save();
    }

    /** @param list<RecurrenceExceptionData> $exceptions */
    private function saveExceptions(Model $record, CalendarModelDefinition $def, array $exceptions, EloquentEventProvider $provider): ActionResult
    {
        $record->setAttribute((string) $def->recurrenceExceptionsField, $this->exceptionsToArray($exceptions));
        $this->bump($record, $def);
        $record->save();

        return ActionResult::success(newVersion: $provider->extractVersion($record, $def));
    }

    /** @return list<RecurrenceExceptionData> */
    private function withException(RecurrenceSeriesData $series, RecurrenceExceptionData $new): array
    {
        $out = array_values(array_filter(
            $series->exceptions,
            fn (RecurrenceExceptionData $e) => $e->originalOccurrenceKey !== $new->originalOccurrenceKey,
        ));
        $out[] = $new;

        return $out;
    }

    /** @param list<RecurrenceExceptionData> $exceptions */
    private function exceptionsToArray(array $exceptions): array
    {
        return array_map(
            fn (RecurrenceExceptionData $e) => ['key' => $e->originalOccurrenceKey, 'kind' => $e->kind, 'replacement' => $e->replacement],
            $exceptions,
        );
    }

    /** @return array{title?: string, description?: string|null, location?: string|null}|null */
    private function presentation(RecurrenceSeriesData $series, string $key): ?array
    {
        foreach ($series->exceptions as $exception) {
            if ($exception->originalOccurrenceKey !== $key || ! is_array($exception->replacement)) {
                continue;
            }
            $out = [];
            foreach (['title', 'description', 'location'] as $name) {
                if (array_key_exists($name, $exception->replacement)) {
                    $out[$name] = $exception->replacement[$name];
                }
            }

            return $out === [] ? null : $out;
        }

        return null;
    }

    private function occurrenceFromReplacement(RecurrenceSeriesData $series, string $sourceKey, string $eventId, RecurrenceExceptionData $exception): ?OccurrenceData
    {
        $replacement = $exception->replacement;
        if (! is_array($replacement) || ! isset($replacement['start'], $replacement['end'])) {
            return null;
        }
        $tz = new DateTimeZone($series->timezone);
        try {
            $start = $series->allDay
                ? new DateTimeImmutable(substr((string) $replacement['start'], 0, 10).' 00:00:00', $tz)
                : new DateTimeImmutable((string) $replacement['start']);
            $end = $series->allDay
                ? new DateTimeImmutable(substr((string) $replacement['end'], 0, 10).' 00:00:00', $tz)
                : new DateTimeImmutable((string) $replacement['end']);
        } catch (\Throwable) {
            return null;
        }
        if ($series->allDay ? $end->format('Y-m-d') <= $start->format('Y-m-d') : $end <= $start) {
            return null;
        }

        return new OccurrenceData(
            new EventReference($sourceKey, $eventId, $exception->originalOccurrenceKey),
            $start,
            $end,
            $this->original($series, $exception->originalOccurrenceKey),
            $series->version,
            true,
            $series->allDay,
        );
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    private function currentTimes(RecurrenceSeriesData $series, string $key): array
    {
        foreach ($series->exceptions as $exception) {
            if ($exception->originalOccurrenceKey === $key && is_array($exception->replacement)) {
                return [new DateTimeImmutable((string) $exception->replacement['start']), new DateTimeImmutable((string) $exception->replacement['end'])];
            }
        }
        $original = $this->original($series, $key);
        $seconds = $series->end->getTimestamp() - $series->start->getTimestamp();

        return [$original, $original->modify('+'.$seconds.' seconds')];
    }

    private function original(RecurrenceSeriesData $series, string $key): DateTimeImmutable
    {
        $format = $series->allDay ? '!Y-m-d' : '!Y-m-d\TH:i:s';
        $parsed = DateTimeImmutable::createFromFormat($format, $key, new DateTimeZone($series->timezone));
        if ($parsed === false) {
            throw new InvalidArgumentException('Invalid occurrence key.');
        }

        return $parsed;
    }

    private function hydrate(Model $record, CalendarModelDefinition $def, CalendarContext $context): ?RecurrenceSeriesData
    {
        if (! $this->hasRule($record, $def)) {
            return null;
        }
        $timezone = $this->timezone($record, $def, $context);
        $tz = new DateTimeZone($timezone);
        $allDay = $this->allDay($record, $def);
        $start = $this->instant($record->getAttribute($def->startField), $allDay, $tz);
        $rawEnd = $def->endField !== null ? $record->getAttribute($def->endField) : null;
        $end = $rawEnd !== null && $rawEnd !== ''
            ? $this->instant($rawEnd, $allDay, $tz)
            : $start->modify('+'.$def->defaultDurationMinutes.' minutes');
        $rawExceptions = $record->getAttribute((string) $def->recurrenceExceptionsField);
        $exceptions = [];
        foreach (is_array($rawExceptions) ? $rawExceptions : [] as $row) {
            if (! is_array($row) || ! isset($row['key'], $row['kind'])) {
                continue;
            }
            $exceptions[] = new RecurrenceExceptionData(
                (string) $row['key'],
                (string) $row['kind'],
                isset($row['replacement']) && is_array($row['replacement']) ? $row['replacement'] : null,
            );
        }

        return new RecurrenceSeriesData(
            (string) $record->getKey(),
            $timezone,
            $start,
            $end,
            RecurrenceRuleData::fromArray((array) $record->getAttribute((string) $def->recurrenceRuleField), $timezone),
            $exceptions,
            $this->version($record, $def),
            $allDay,
        );
    }

    private function timezone(Model $record, CalendarModelDefinition $def, CalendarContext $context): string
    {
        if ($def->timezoneField !== null) {
            $raw = $record->getAttribute($def->timezoneField);
            if (is_string($raw) && in_array($raw, timezone_identifiers_list(), true)) {
                return $raw;
            }
        }

        return in_array($context->timezone, timezone_identifiers_list(), true) ? $context->timezone : 'UTC';
    }

    private function allDay(Model $record, CalendarModelDefinition $def): bool
    {
        if ($def->allDayField === null) {
            return $def->defaultAllDay;
        }

        return (bool) ($record->getAttribute($def->allDayField) ?? $def->defaultAllDay);
    }

    private function instant(mixed $value, bool $allDay, DateTimeZone $tz): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            $instant = DateTimeImmutable::createFromInterface($value);
        } else {
            $instant = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
        }
        if ($allDay) {
            return new DateTimeImmutable($instant->format('Y-m-d').' 00:00:00', $tz);
        }

        return $instant->setTimezone($tz);
    }

    private function format(DateTimeImmutable $instant, bool $allDay): string
    {
        if ($allDay) {
            return $instant->format('Y-m-d').' 00:00:00';
        }

        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function bump(Model $record, CalendarModelDefinition $def): void
    {
        if ($def->versionField !== null) {
            $record->setAttribute($def->versionField, ((int) $record->getAttribute($def->versionField)) + 1);
        }
    }

    private function version(Model $record, CalendarModelDefinition $def): string
    {
        if ($def->versionField !== null) {
            $value = $record->getAttribute($def->versionField);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return '1';
    }
}
