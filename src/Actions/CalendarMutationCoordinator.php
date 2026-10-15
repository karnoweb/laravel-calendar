<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use Karnoweb\Calendar\Concurrency\OperationOutcomeResolver;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Enums\ActionStatus;

/**
 * Runs one mutation and turns the result into UI instructions: toast, field errors, whether the
 * visible range must be refetched, whether the form may close. Success is never assumed.
 */
final class CalendarMutationCoordinator
{
    public function __construct(
        private readonly ActionDispatcher $dispatcher,
        private readonly ActionResultPresenter $presenter,
        private readonly OperationOutcomeResolver $outcomes,
    ) {}

    public function run(CalendarDefinition $definition, CalendarContext $context, ActionCommand $command): MutationOutcome
    {
        $result = $this->dispatcher->dispatch($definition, $context, $command);

        return new MutationOutcome(
            $result,
            $this->presenter->present($result),
            refetch: $result->isSuccess() || $this->outcomes->requiresRefetch($result),
            // validation errors keep the form (and the user's input) open
            closeForm: $result->isSuccess() || in_array($result->status, [ActionStatus::Conflict, ActionStatus::NotFound, ActionStatus::Forbidden, ActionStatus::ReadOnly], true),
        );
    }
}
