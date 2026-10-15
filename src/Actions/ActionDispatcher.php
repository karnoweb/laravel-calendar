<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use Illuminate\Contracts\Container\Container;
use Karnoweb\Calendar\Concurrency\OperationOutcomeResolver;
use Karnoweb\Calendar\Contracts\ActionExecutionJournal;
use Karnoweb\Calendar\Contracts\ActionHandler;
use Karnoweb\Calendar\Contracts\CalendarCache;
use Karnoweb\Calendar\Contracts\CalendarOperationHooks;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Exceptions\CalendarNotFound;
use Karnoweb\Calendar\Exceptions\CalendarRateLimited;
use Karnoweb\Calendar\Observability\CalendarTelemetry;
use Karnoweb\Calendar\Security\CalendarAuthorizationGuard;
use Karnoweb\Calendar\Security\CalendarRateLimitGuard;
use Karnoweb\Calendar\Security\EventReferenceGuard;
use Throwable;

/**
 * The only write path. Order matters (fail closed):
 * rate limit -> package policy -> re-resolve the target through the provider -> validate -> journal ->
 * host handler -> outcome normalisation -> hooks/cache. Nothing here ever invokes a method named by the client.
 */
final class ActionDispatcher
{
    public function __construct(
        private readonly Container $container,
        private readonly CalendarAuthorizationGuard $guard,
        private readonly CalendarRateLimitGuard $limits,
        private readonly EventReferenceGuard $references,
        private readonly CalendarActionValidator $validator,
        private readonly ActionExecutionJournal $journal,
        private readonly OperationOutcomeResolver $outcomes,
        private readonly CalendarOperationHooks $hooks,
        private readonly CalendarCache $cache,
        private readonly CalendarTelemetry $telemetry,
    ) {}

    public function dispatch(CalendarDefinition $definition, CalendarContext $context, ActionCommand $command): ActionResult
    {
        try {
            $this->limits->hit($context, 'write');
            $this->limits->assertPayload($definition, $command->payload);
        } catch (CalendarRateLimited $e) {
            return $this->outcomes->fromThrowable($e);
        }

        $sourceKey = $command->targetSourceKey();
        if (($denial = $this->guard->writeDenial($definition, $context, $command->action, $sourceKey)) !== null) {
            return ActionResult::failure($denial, $denial === ActionStatus::ReadOnly ? 'calendar::calendar.messages.read_only' : ($denial === ActionStatus::Unsupported ? 'calendar::calendar.messages.unsupported' : 'calendar::calendar.messages.forbidden'));
        }

        // Replay protection comes BEFORE target resolution and the version check: a retried, already-applied
        // operation (e.g. a delete whose target is gone, or a move whose version has since advanced) must return
        // its recorded outcome instead of a misleading not-found/conflict.
        if (($previous = $this->journal->find($command->operationId, $context)) !== null) {
            return $previous;
        }

        $event = null;
        if ($command->reference !== null) {
            try {
                $event = $this->references->resolve($definition, $context, $command->reference);
            } catch (CalendarAccessDenied|CalendarNotFound $e) {
                return $this->outcomes->fromThrowable($e);
            }
            if ($command->expectedVersion !== null && $event->version !== null && ! hash_equals($event->version, $command->expectedVersion)) {
                return ActionResult::failure(ActionStatus::Conflict, 'calendar::calendar.messages.conflict');
            }
        }

        if (($invalid = $this->validator->validate($command, $event, $definition, $context)) !== null) {
            return $invalid;
        }

        $result = $this->execute($definition, $context, $command, $event);

        if ($this->isFinal($result)) {
            try {
                $this->journal->record($command->operationId, $context, $result);
            } catch (Throwable) {
                // The journal is advisory for the core; a host that needs atomic dedupe does it in its handler transaction.
            }
        }
        if ($result->isSuccess()) {
            $this->afterSuccess($definition, $context, $command, $result);
        }

        return $result;
    }

    private function execute(CalendarDefinition $definition, CalendarContext $context, ActionCommand $command, ?CalendarEventData $event): ActionResult
    {
        try {
            /** @var ActionHandler $handler */
            $handler = $this->container->make((string) $definition->handlerClass);

            return $this->telemetry->measure('action', fn () => $handler->execute($command, $context), [
                'calendar' => $context->calendarKey, 'action' => $command->action->value, 'source' => $command->targetSourceKey(),
            ]);
        } catch (Throwable $e) {
            // The handler may or may not have committed before failing: unknown unless it said otherwise.
            return $this->outcomes->fromThrowable($e);
        }
    }

    private function isFinal(ActionResult $result): bool
    {
        return ! in_array($result->status, [ActionStatus::UnknownOutcome, ActionStatus::RetryableError, ActionStatus::RateLimited], true);
    }

    private function afterSuccess(CalendarDefinition $definition, CalendarContext $context, ActionCommand $command, ActionResult $result): void
    {
        try {
            $this->cache->invalidateScope($definition->key, $context->scopeKey);
        } catch (Throwable) {
            // cache invalidation failure must not turn a committed success into a failure
        }
        try {
            $this->hooks->afterCommit($command, $result, $context);
        } catch (Throwable $e) {
            $this->telemetry->record('hook.failed', ['calendar' => $context->calendarKey, 'exception' => $e::class]);
        }
    }
}
