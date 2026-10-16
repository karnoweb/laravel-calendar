<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Karnoweb\Calendar\Contracts\ActionHandler;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarModelDefinition;
use Karnoweb\Calendar\Data\CreateEventInput;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\MoveEventInput;
use Karnoweb\Calendar\Data\ResizeEventInput;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Google\GoogleCalendarHandler;
use Karnoweb\Calendar\Providers\EloquentEventProvider;
use Karnoweb\Calendar\Support\CalendarModelRegistry;

/**
 * Default ActionHandler for Eloquent models registered via HasCalendarEvents + CalendarModelRegistry.
 * Enforces source registration, read scope, per-record capabilities, authorization callback, and
 * optimistic concurrency inside a database transaction.
 */
final class EloquentActionHandler implements ActionHandler
{
    /** @var array<string, array<string, true>> */
    private static array $columnCache = [];

    public function __construct(
        private readonly CalendarModelRegistry $models,
        private readonly Container $container,
    ) {}

    public function execute(ActionCommand $command, CalendarContext $context): ActionResult
    {
        $sourceKey = $command->targetSourceKey();

        if ($sourceKey === 'google') {
            return $this->container->make(GoogleCalendarHandler::class)->execute($command, $context);
        }

        if (! $this->models->has($sourceKey)) {
            return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.unsupported');
        }

        $def = $this->models->definition($sourceKey);

        if ($def->handlerClass !== null && $def->handlerClass !== self::class) {
            /** @var ActionHandler $custom */
            $custom = $this->container->make($def->handlerClass);

            return $custom->execute($command, $context);
        }

        if ($def->readOnly || ! $def->capabilities->allows($command->action->capability())) {
            return ActionResult::failure(ActionStatus::ReadOnly, 'calendar::calendar.messages.read_only');
        }

        if (! $def->hasScopeRule() || $context->isAnonymous()) {
            return ActionResult::failure(ActionStatus::Forbidden, 'calendar::calendar.messages.forbidden');
        }

        /** @var EloquentEventProvider $provider */
        $provider = $this->container->make(EloquentEventProvider::class, ['sourceKey' => $sourceKey]);

        return DB::transaction(fn (): ActionResult => $this->runInTransaction($command, $context, $sourceKey, $def, $provider));
    }

    private function runInTransaction(
        ActionCommand $command,
        CalendarContext $context,
        string $sourceKey,
        CalendarModelDefinition $def,
        EloquentEventProvider $provider,
    ): ActionResult {
        if ($command->action === CalendarAction::Create) {
            return $this->handleCreate($command, $context, $sourceKey, $def, $provider);
        }

        $ref = $command->reference;
        if ($ref === null || $ref->sourceKey !== $sourceKey || $ref->occurrenceKey !== null) {
            return ActionResult::failure(ActionStatus::NotFound, 'calendar::calendar.messages.not_found');
        }

        try {
            $q = $provider->scopedQuery($sourceKey, $def, $context)->lockForUpdate();
        } catch (CalendarAccessDenied) {
            return ActionResult::failure(ActionStatus::Forbidden, 'calendar::calendar.messages.forbidden');
        }

        $model = $q->getModel();
        if ($model->getKeyType() === 'int' && ! ctype_digit($ref->eventId)) {
            return ActionResult::failure(ActionStatus::NotFound, 'calendar::calendar.messages.not_found');
        }

        /** @var Model|null $record */
        $record = $q->where($model->getTable().'.'.$model->getKeyName(), $ref->eventId)->first();
        if ($record === null) {
            return ActionResult::failure(ActionStatus::NotFound, 'calendar::calendar.messages.not_found');
        }

        $recordCaps = $provider->resolveCapabilities($record, $def, $context);
        if (! $recordCaps->allows($command->action->capability())) {
            return ActionResult::failure(ActionStatus::ReadOnly, 'calendar::calendar.messages.read_only');
        }

        if ($def->authorize !== null && ! ($def->authorize)($command->action, $record, $context, $command->payload)) {
            return ActionResult::failure(ActionStatus::Forbidden, 'calendar::calendar.messages.forbidden');
        }

        $currentVersion = $provider->extractVersion($record, $def);
        if ($command->expectedVersion !== null && $currentVersion !== '' && ! hash_equals($currentVersion, $command->expectedVersion)) {
            return ActionResult::failure(ActionStatus::Conflict, 'calendar::calendar.messages.conflict');
        }

        switch ($command->action) {
            case CalendarAction::Delete:
                $record->delete();

                return ActionResult::success([$ref]);

            case CalendarAction::Update:
                $in = CreateEventInput::fromCommand($command, $context->timezone);
                if ($in->recurrence !== null && ! $def->supportsRecurrence) {
                    return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.unsupported');
                }
                $this->applyInputAttributes($record, $def, $in);
                break;

            case CalendarAction::Move:
                $m = MoveEventInput::fromCommand($command, $context->timezone);
                $record->setAttribute($def->startField, $this->formatTimestamp($m->start, $m->allDay));
                if ($def->endField !== null && $this->hasColumn($record, $def->endField)) {
                    $record->setAttribute($def->endField, $this->formatTimestamp($m->end, $m->allDay));
                }
                if ($def->allDayField !== null && $this->hasColumn($record, $def->allDayField)) {
                    $record->setAttribute($def->allDayField, $m->allDay);
                }
                break;

            case CalendarAction::Resize:
                if ($def->endField === null || ! $this->hasColumn($record, $def->endField)) {
                    return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.unsupported');
                }
                $r = ResizeEventInput::fromCommand($command, $context->timezone);
                $record->setAttribute($def->endField, $this->formatTimestamp($r->end, false));
                break;

            default:
                return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.unsupported');
        }

        if ($def->writeMapper !== null) {
            ($def->writeMapper)($record, $command->payload, $command->action, $context);
        }

        if ($def->versionField !== null && $this->hasColumn($record, $def->versionField)) {
            $record->setAttribute($def->versionField, ((int) $record->getAttribute($def->versionField)) + 1);
        }

        $record->save();

        return ActionResult::success([$ref], newVersion: $provider->extractVersion($record, $def));
    }

    private function handleCreate(
        ActionCommand $command,
        CalendarContext $context,
        string $sourceKey,
        CalendarModelDefinition $def,
        EloquentEventProvider $provider,
    ): ActionResult {
        if ($def->authorize !== null && ! ($def->authorize)(CalendarAction::Create, null, $context, $command->payload)) {
            return ActionResult::failure(ActionStatus::Forbidden, 'calendar::calendar.messages.forbidden');
        }

        $in = CreateEventInput::fromCommand($command, $context->timezone);
        if ($in->recurrence !== null && ! $def->supportsRecurrence) {
            return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.unsupported');
        }

        $modelClass = $this->models->modelClass($sourceKey);
        /** @var Model $record */
        $record = new $modelClass;

        if ($def->scopeField !== null && $this->hasColumn($record, $def->scopeField)) {
            $record->setAttribute($def->scopeField, $context->scopeKey);
        }
        if ($def->ownerField !== null && $context->actorId !== null && $this->hasColumn($record, $def->ownerField)) {
            $record->setAttribute($def->ownerField, $context->actorId);
        }
        if ($def->tenantField !== null && $context->tenantId !== null && $this->hasColumn($record, $def->tenantField)) {
            $record->setAttribute($def->tenantField, $context->tenantId);
        }
        if ($def->versionField !== null && $this->hasColumn($record, $def->versionField)) {
            $record->setAttribute($def->versionField, 1);
        }

        $this->applyInputAttributes($record, $def, $in);

        if ($def->writeMapper !== null) {
            ($def->writeMapper)($record, $command->payload, CalendarAction::Create, $context);
        }

        $record->save();

        $ref = new EventReference($sourceKey, (string) $record->getKey());

        return ActionResult::success([$ref], newVersion: $provider->extractVersion($record, $def));
    }

    private function applyInputAttributes(Model $record, CalendarModelDefinition $def, CreateEventInput $in): void
    {
        $titleCol = $def->titleFieldName();
        if ($this->hasColumn($record, $titleCol)) {
            $record->setAttribute($titleCol, $in->title);
        }

        if ($def->descriptionField !== null && $this->hasColumn($record, $def->descriptionField)) {
            $record->setAttribute($def->descriptionField, $in->description);
        }
        if ($def->locationField !== null && $this->hasColumn($record, $def->locationField)) {
            $record->setAttribute($def->locationField, $in->location);
        }

        if ($def->statusField !== null && $this->hasColumn($record, $def->statusField)) {
            $status = $in->status ?? (isset($in->extra['status']) && is_string($in->extra['status']) ? $in->extra['status'] : null);
            if ($status !== null && ($def->statusOptions === [] || array_key_exists($status, $def->statusOptions))) {
                $record->setAttribute($def->statusField, $status);
            } elseif (! $record->exists && $record->getAttribute($def->statusField) === null && $def->defaultStatus() !== null) {
                $record->setAttribute($def->statusField, $def->defaultStatus());
            }
        }

        if ($def->categoryField !== null && $this->hasColumn($record, $def->categoryField)) {
            $category = $in->category ?? (isset($in->extra['category']) && is_string($in->extra['category']) ? $in->extra['category'] : null);
            if ($category !== null && ($def->categoryOptions === [] || array_key_exists($category, $def->categoryOptions))) {
                $record->setAttribute($def->categoryField, $category);
            } elseif (! $record->exists && $record->getAttribute($def->categoryField) === null && $def->defaultCategory() !== null) {
                $record->setAttribute($def->categoryField, $def->defaultCategory());
            }
        }

        if ($def->allDayField !== null && $this->hasColumn($record, $def->allDayField)) {
            $record->setAttribute($def->allDayField, $in->allDay);
        }
        if ($def->movableField !== null && $this->hasColumn($record, $def->movableField)) {
            $record->setAttribute($def->movableField, $in->movable);
        }
        if ($def->timezoneField !== null && $this->hasColumn($record, $def->timezoneField)) {
            $record->setAttribute($def->timezoneField, $in->timezone);
        }

        $record->setAttribute($def->startField, $this->formatTimestamp($in->start, $in->allDay));
        if ($def->endField !== null && $this->hasColumn($record, $def->endField)) {
            $record->setAttribute($def->endField, $this->formatTimestamp($in->end, $in->allDay));
        }

        if ($def->formFields !== null) {
            foreach ($def->formFields as $field) {
                $name = $field['name'] ?? null;
                if (is_string($name) && array_key_exists($name, $in->extra) && $this->hasColumn($record, $name)) {
                    $record->setAttribute($name, $in->extra[$name]);
                }
            }
        }
    }

    private function formatTimestamp(DateTimeImmutable $instant, bool $allDay): string
    {
        if ($allDay) {
            return $instant->format('Y-m-d').' 00:00:00';
        }

        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function hasColumn(Model $record, string $column): bool
    {
        $cacheKey = ($record->getConnectionName() ?? 'default').':'.$record->getTable();
        if (! isset(self::$columnCache[$cacheKey])) {
            $cols = $record->getConnection()->getSchemaBuilder()->getColumnListing($record->getTable());
            self::$columnCache[$cacheKey] = array_fill_keys($cols, true);
        }

        return isset(self::$columnCache[$cacheKey][$column]);
    }
}
