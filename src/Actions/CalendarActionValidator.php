<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use InvalidArgumentException;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\CreateEventInput;
use Karnoweb\Calendar\Data\MoveEventInput;
use Karnoweb\Calendar\Data\ResizeEventInput;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Exceptions\InvalidActionPayload;
use Karnoweb\Calendar\Recurrence\RecurrenceLimitPolicy;

/** Validates payload shape, capabilities and recurrence coherence BEFORE the host handler is reached. */
final class CalendarActionValidator
{
    /** @return ActionResult|null null = valid */
    public function validate(ActionCommand $command, ?CalendarEventData $event, CalendarDefinition $definition, CalendarContext $context): ?ActionResult
    {
        $action = $command->action;

        if ($event !== null) {
            if (! $event->capabilities->allows($action->capability())) {
                return ActionResult::failure($event->isReadOnly() ? ActionStatus::ReadOnly : ActionStatus::Forbidden, 'calendar::calendar.messages.read_only');
            }
            if ($command->reference?->occurrenceKey !== null && ! $event->isRecurring && $action->isRecurrenceAction()) {
                return ActionResult::failure(ActionStatus::ValidationError, 'calendar::calendar.messages.not_recurring');
            }
        }

        if (in_array($action, [CalendarAction::OccurrenceUpdate, CalendarAction::OccurrenceDelete, CalendarAction::SeriesSplit], true)
            && $command->reference?->occurrenceKey === null) {
            return ActionResult::failure(ActionStatus::ValidationError, 'calendar::calendar.messages.occurrence_required');
        }
        if ($event !== null && in_array($action, [CalendarAction::SeriesUpdate, CalendarAction::SeriesDelete, CalendarAction::OccurrenceUpdate, CalendarAction::OccurrenceDelete, CalendarAction::SeriesSplit], true) && ! $event->isRecurring) {
            return ActionResult::failure(ActionStatus::ValidationError, 'calendar::calendar.messages.not_recurring');
        }

        try {
            $policy = RecurrenceLimitPolicy::fromSettings($definition->settings);
            switch ($action) {
                case CalendarAction::Create:
                case CalendarAction::Update:
                case CalendarAction::SeriesUpdate:
                case CalendarAction::OccurrenceUpdate:
                    $input = CreateEventInput::fromCommand($command, $context->timezone);
                    $this->assertRecurrenceAllowed($input, $definition, $policy);
                    break;
                case CalendarAction::SeriesSplit:
                    if (($command->payload['mode'] ?? 'update') !== 'delete') {
                        $input = CreateEventInput::fromCommand($command, $context->timezone);
                        $this->assertRecurrenceAllowed($input, $definition, $policy);
                    }
                    break;
                case CalendarAction::Move:
                    $move = MoveEventInput::fromCommand($command, $context->timezone);
                    if ($event !== null && $event->allDay !== $move->allDay) {
                        return ActionResult::failure(ActionStatus::ValidationError, 'calendar::calendar.messages.invalid_move');
                    }
                    break;
                case CalendarAction::Resize:
                    ResizeEventInput::fromCommand($command, $context->timezone);
                    if ($event !== null && $event->allDay) {
                        return ActionResult::failure(ActionStatus::Unsupported, 'calendar::calendar.messages.resize_all_day');
                    }
                    break;
                default:
                    break;
            }
        } catch (InvalidActionPayload $e) {
            return ActionResult::failure(ActionStatus::ValidationError, 'calendar::calendar.messages.validation', $e->fieldErrors);
        } catch (InvalidArgumentException) {
            return ActionResult::failure(ActionStatus::ValidationError, 'calendar::calendar.messages.validation');
        }

        return null;
    }

    private function assertRecurrenceAllowed(CreateEventInput $input, CalendarDefinition $definition, RecurrenceLimitPolicy $policy): void
    {
        if ($input->recurrence === null) {
            return;
        }
        if (! $definition->settings->feature('recurrence')) {
            throw new InvalidActionPayload(['recurrence' => 'calendar::calendar.validation.recurrence_disabled']);
        }
        $policy->assertRule($input->recurrence);
    }
}
