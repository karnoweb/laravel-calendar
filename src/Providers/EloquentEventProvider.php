<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Providers;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\CalendarModelDefinition;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Security\AllowedCssToken;
use Karnoweb\Calendar\Support\CalendarModelRegistry;

/**
 * Automatic SQL-backed EventProvider for Eloquent models registered in CalendarModelRegistry.
 * Applies scope/ownership, half-open date range overlap, search, filters and pagination directly in SQL
 * before converting only the current page's records into CalendarEventData.
 */
final class EloquentEventProvider implements EventProvider
{
    public function __construct(
        private readonly CalendarModelRegistry $models,
        private readonly Container $container,
        private readonly ?string $sourceKey = null,
    ) {}

    public function fetch(EventQuery $query, CalendarContext $context): EventPage
    {
        $sourceKey = $this->resolveSourceKey();
        $def = $this->models->definition($sourceKey);

        if ($def->providerClass !== null && $def->providerClass !== self::class) {
            /** @var EventProvider $custom */
            $custom = $this->container->make($def->providerClass);

            return $custom->fetch($query, $context);
        }

        $q = $this->scopedQuery($sourceKey, $def, $context);
        $table = $q->getModel()->getTable();
        $keyName = $q->getModel()->getKeyName();

        $this->applyRangeOverlap($q, $table, $def, $query);
        $this->applySearch($q, $table, $def, $query->search);
        $this->applyFilters($q, $table, $def, $query->filters, $context);

        $offset = $query->cursor === null ? 0 : max(0, (int) $query->cursor);
        $rows = $q->orderBy($table.'.'.$def->startField)
            ->orderBy($table.'.'.$keyName)
            ->offset($offset)
            ->limit($query->limit + 1)
            ->get();

        $more = $rows->count() > $query->limit;
        $rows = $rows->take($query->limit);

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->toEvent($row, $def, $sourceKey, $context);
        }

        return new EventPage(
            $events,
            $more,
            $more ? (string) ($offset + $query->limit) : null,
            null,
            $query->requestKey,
        );
    }

    public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData
    {
        $sourceKey = $this->sourceKey ?? $reference->sourceKey;
        if (! $this->models->has($sourceKey) || $reference->sourceKey !== $sourceKey) {
            return null;
        }

        $def = $this->models->definition($sourceKey);
        if ($def->providerClass !== null && $def->providerClass !== self::class) {
            /** @var EventProvider $custom */
            $custom = $this->container->make($def->providerClass);

            return $custom->find($reference, $context);
        }

        if ($reference->occurrenceKey !== null) {
            return null;
        }

        $q = $this->scopedQuery($sourceKey, $def, $context);
        $model = $q->getModel();
        if ($model->getKeyType() === 'int' && ! ctype_digit($reference->eventId)) {
            return null;
        }

        $record = $q->where($model->getTable().'.'.$model->getKeyName(), $reference->eventId)->first();

        return $record === null ? null : $this->toEvent($record, $def, $sourceKey, $context);
    }

    /**
     * Build a query pre-filtered by the model's server-side read scope (fail-closed if none is defined).
     *
     * @return Builder<Model>
     */
    public function scopedQuery(string $sourceKey, CalendarModelDefinition $def, CalendarContext $context): Builder
    {
        if (! $def->hasScopeRule()) {
            throw new CalendarAccessDenied("Calendar model source [{$sourceKey}] has no server-side read scope rule.");
        }
        if (! $def->publicRead && $context->isAnonymous()) {
            throw new CalendarAccessDenied("Anonymous read denied for calendar model source [{$sourceKey}].");
        }

        $modelClass = $this->models->modelClass($sourceKey);
        /** @var Builder<Model> $q */
        $q = $modelClass::query();
        $table = $q->getModel()->getTable();

        if ($def->with !== []) {
            $q->with($def->with);
        }

        if ($def->scopeField !== null) {
            if ($context->scopeKey === '') {
                throw new CalendarAccessDenied('Empty scope key.');
            }
            $q->where($this->qualify($table, $def->scopeField), $context->scopeKey);
        }

        if ($def->tenantField !== null) {
            if ($context->tenantId === null || $context->tenantId === '') {
                throw new CalendarAccessDenied('Missing tenant context.');
            }
            $q->where($this->qualify($table, $def->tenantField), $context->tenantId);
        }

        if ($def->ownerField !== null) {
            if ($context->actorId === null || $context->actorId === '') {
                throw new CalendarAccessDenied('Missing actor context.');
            }
            $q->where($this->qualify($table, $def->ownerField), $context->actorId);
        }

        if ($def->readScope !== null) {
            ($def->readScope)($q, $context);
        }

        return $q;
    }

    public function resolveCapabilities(Model $record, CalendarModelDefinition $def, CalendarContext $context): ActionCapabilities
    {
        if ($def->readOnly) {
            return ActionCapabilities::none();
        }

        $base = $def->capabilities;
        if ($def->movableField !== null) {
            $movableAttr = $record->getAttribute($def->movableField);
            if ($movableAttr !== null && ! (bool) $movableAttr) {
                $base = new ActionCapabilities($base->canCreate, $base->canEdit, $base->canDelete, false, false);
            }
        }

        if ($def->capabilitiesResolver !== null) {
            $resolved = ($def->capabilitiesResolver)($record, $context, $base);
            if ($resolved instanceof ActionCapabilities) {
                return $resolved;
            }
        }

        return $base;
    }

    public function toEvent(Model $record, CalendarModelDefinition $def, string $sourceKey, CalendarContext $context): CalendarEventData
    {
        $utc = new DateTimeZone('UTC');
        $allDay = $def->allDayField !== null
            ? (bool) ($record->getAttribute($def->allDayField) ?? $def->defaultAllDay)
            : $def->defaultAllDay;

        $start = $this->parseDateTime($record->getAttribute($def->startField), $allDay, $utc);
        $rawEnd = $def->endField !== null ? $record->getAttribute($def->endField) : null;

        if ($rawEnd !== null && $rawEnd !== '') {
            $end = $this->parseDateTime($rawEnd, $allDay, $utc);
        } elseif ($allDay) {
            $days = max(1, (int) ceil($def->defaultDurationMinutes / 1440));
            $end = $start->modify("+{$days} days");
        } else {
            $end = $start->modify("+{$def->defaultDurationMinutes} minutes");
        }

        if ($allDay && $end->format('Y-m-d') <= $start->format('Y-m-d')) {
            $end = $start->modify('+1 day');
        } elseif (! $allDay && $end <= $start) {
            $end = $start->modify("+{$def->defaultDurationMinutes} minutes");
        }

        $title = $def->titleField instanceof Closure
            ? (string) ($def->titleField)($record, $context)
            : (string) ($record->getAttribute($def->titleField) ?? '');

        $description = $def->descriptionField !== null && $record->getAttribute($def->descriptionField) !== null
            ? (string) $record->getAttribute($def->descriptionField)
            : null;
        $location = $def->locationField !== null && $record->getAttribute($def->locationField) !== null
            ? (string) $record->getAttribute($def->locationField)
            : null;
        $status = $def->statusField !== null && $record->getAttribute($def->statusField) !== null
            ? (string) $record->getAttribute($def->statusField)
            : null;
        $category = $def->categoryField !== null && $record->getAttribute($def->categoryField) !== null
            ? (string) $record->getAttribute($def->categoryField)
            : null;

        $color = null;
        if ($def->colorField instanceof Closure) {
            $color = ($def->colorField)($record, $context);
        } elseif (is_string($def->colorField) && $def->colorField !== '') {
            $rawColor = $record->getAttribute($def->colorField);
            $color = is_string($rawColor) && $rawColor !== '' ? $rawColor : null;
        }
        if ($color === null && $status !== null && isset($def->statusColors[$status])) {
            $color = $def->statusColors[$status];
        }
        if ($color === null && $category !== null && isset($def->categoryColors[$category])) {
            $color = $def->categoryColors[$category];
        }
        if ($color !== null && ! AllowedCssToken::isValidColor($color)) {
            $color = null;
        }

        $url = null;
        if ($def->urlField instanceof Closure) {
            $rawUrl = ($def->urlField)($record, $context);
            $url = is_string($rawUrl) && $rawUrl !== '' ? $rawUrl : null;
        } elseif (is_string($def->urlField) && $def->urlField !== '') {
            $rawUrl = $record->getAttribute($def->urlField);
            $url = is_string($rawUrl) && $rawUrl !== '' ? $rawUrl : null;
        }

        $version = $this->extractVersion($record, $def);

        return new CalendarEventData(
            reference: new EventReference($sourceKey, (string) $record->getKey()),
            title: $title,
            start: $start,
            end: $end,
            allDay: $allDay,
            version: $version,
            capabilities: $this->resolveCapabilities($record, $def, $context),
            description: $description,
            location: $location,
            color: $color,
            url: $url,
            status: $status,
            category: $category,
            isRecurring: false,
        );
    }

    public function extractVersion(Model $record, CalendarModelDefinition $def): string
    {
        if ($def->versionField !== null) {
            $val = $record->getAttribute($def->versionField);
            if ($val !== null && $val !== '') {
                return (string) $val;
            }
        }
        $updated = $record->getAttribute('updated_at');
        if ($updated instanceof DateTimeInterface) {
            return (string) $updated->getTimestamp();
        }

        return '1';
    }

    /** @param Builder<Model> $q */
    private function applyRangeOverlap(Builder $q, string $table, CalendarModelDefinition $def, EventQuery $query): void
    {
        $startCol = $this->qualify($table, $def->startField);
        $utcStart = $query->range->startInclusive->format('Y-m-d H:i:s');
        $utcEnd = $query->range->endExclusive->format('Y-m-d H:i:s');
        $fallbackStart = $query->range->startInclusive
            ->modify("-{$def->defaultDurationMinutes} minutes")
            ->format('Y-m-d H:i:s');

        if ($def->endField === null) {
            $q->where($startCol, '<', $utcEnd)
                ->where($startCol, '>', $fallbackStart);

            return;
        }

        $endCol = $this->qualify($table, $def->endField);
        $q->where($startCol, '<', $utcEnd)
            ->where(function (Builder $sub) use ($endCol, $startCol, $utcStart, $fallbackStart): void {
                $sub->where($endCol, '>', $utcStart)
                    ->orWhere(function (Builder $nullEnd) use ($endCol, $startCol, $fallbackStart): void {
                        $nullEnd->whereNull($endCol)
                            ->where($startCol, '>', $fallbackStart);
                    });
            });
    }

    /** @param Builder<Model> $q */
    private function applySearch(Builder $q, string $table, CalendarModelDefinition $def, string $search): void
    {
        if ($search === '' || $def->searchableFields === []) {
            return;
        }

        $escaped = addcslashes($search, '%_\\');
        $q->where(function (Builder $sub) use ($table, $def, $escaped): void {
            foreach ($def->searchableFields as $idx => $field) {
                if (! is_string($field) || $field === '') {
                    continue;
                }
                $col = $this->qualify($table, $field);
                if ($idx === 0) {
                    $sub->where($col, 'like', '%'.$escaped.'%');
                } else {
                    $sub->orWhere($col, 'like', '%'.$escaped.'%');
                }
            }
        });
    }

    /**
     * @param  Builder<Model>  $q
     * @param  array<string, string|list<string>>  $filters
     */
    private function applyFilters(Builder $q, string $table, CalendarModelDefinition $def, array $filters, CalendarContext $context): void
    {
        foreach ($filters as $field => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }
            if (isset($def->filterCallbacks[$field])) {
                ($def->filterCallbacks[$field])($q, $value, $context);

                continue;
            }
            if ($field === 'status' && $def->statusField !== null) {
                $q->where($this->qualify($table, $def->statusField), $value);

                continue;
            }
            if ($field === 'category' && $def->categoryField !== null) {
                $q->where($this->qualify($table, $def->categoryField), $value);

                continue;
            }
            if (preg_match('/^[a-z][a-z0-9_]{0,39}$/', $field)) {
                $q->where($this->qualify($table, $field), $value);
            }
        }
    }

    private function parseDateTime(mixed $value, bool $allDay, DateTimeZone $utc): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return $allDay
                ? new DateTimeImmutable($value->format('Y-m-d').' 00:00:00', $utc)
                : DateTimeImmutable::createFromInterface($value)->setTimezone($utc);
        }
        $str = (string) $value;
        if ($allDay) {
            $date = substr($str, 0, 10);

            return new DateTimeImmutable($date.' 00:00:00', $utc);
        }

        return (new DateTimeImmutable($str, $utc))->setTimezone($utc);
    }

    private function qualify(string $table, string $column): string
    {
        return str_contains($column, '.') ? $column : $table.'.'.$column;
    }

    private function resolveSourceKey(): string
    {
        if ($this->sourceKey !== null && $this->sourceKey !== '') {
            return $this->sourceKey;
        }
        $keys = $this->models->keys();
        if (count($keys) === 1) {
            return $keys[0];
        }

        throw new CalendarAccessDenied('Ambiguous Eloquent event provider source key.');
    }
}
