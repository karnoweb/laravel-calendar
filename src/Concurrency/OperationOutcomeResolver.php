<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Concurrency;

use Illuminate\Database\QueryException;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Exceptions\CalendarConflict;
use Karnoweb\Calendar\Exceptions\CalendarNotFound;
use Karnoweb\Calendar\Exceptions\CalendarRateLimited;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;
use Throwable;

/**
 * Converts anything a host handler may throw into a standard result. Unknown failures after the
 * handler started are UNKNOWN_OUTCOME: success is never assumed and the UI must refetch.
 */
final class OperationOutcomeResolver
{
    public function fromThrowable(Throwable $e): ActionResult
    {
        return match (true) {
            $e instanceof CalendarAccessDenied => ActionResult::failure(ActionStatus::Forbidden, 'calendar::calendar.messages.forbidden'),
            $e instanceof CalendarConflict => ActionResult::failure(ActionStatus::Conflict, 'calendar::calendar.messages.conflict'),
            $e instanceof CalendarNotFound => ActionResult::failure(ActionStatus::NotFound, 'calendar::calendar.messages.not_found'),
            $e instanceof CalendarRateLimited => ActionResult::failure(ActionStatus::RateLimited, 'calendar::calendar.messages.rate_limited'),
            $e instanceof InvalidCalendarDate => ActionResult::failure(ActionStatus::ValidationError, 'calendar::calendar.messages.invalid_date'),
            $e instanceof QueryException && $this->isDeadlockOrLock($e) => ActionResult::failure(ActionStatus::RetryableError, 'calendar::calendar.messages.retryable'),
            default => ActionResult::failure(ActionStatus::UnknownOutcome, 'calendar::calendar.messages.unknown_outcome'),
        };
    }

    /** Whether the UI must reload from the server before showing anything as final. */
    public function requiresRefetch(ActionResult $result): bool
    {
        return in_array($result->status, [
            ActionStatus::Conflict, ActionStatus::UnknownOutcome, ActionStatus::RetryableError,
            ActionStatus::NotFound, ActionStatus::DomainRejected,
        ], true);
    }

    private function isDeadlockOrLock(QueryException $e): bool
    {
        $m = strtolower($e->getMessage());

        return str_contains($m, 'deadlock') || str_contains($m, 'lock wait') || str_contains($m, 'database is locked');
    }
}
