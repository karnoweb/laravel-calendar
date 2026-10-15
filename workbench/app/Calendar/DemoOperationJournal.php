<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Karnoweb\Calendar\Contracts\ActionExecutionJournal;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Enums\ActionStatus;

/**
 * Idempotency store: a repeated operation id within the same scope replays the recorded final result
 * instead of executing again. The write happens AFTER the handler committed, so a crash between the two
 * can still double-apply; therefore this journal honestly reports guaranteesExactlyOnce() === false.
 */
final class DemoOperationJournal implements ActionExecutionJournal
{
    public function find(string $operationId, CalendarContext $context): ?ActionResult
    {
        $row = DB::table('demo_action_journal')->where('scope_key', $context->scopeKey)->where('operation_id', $operationId)->first();
        if ($row === null) {
            return null;
        }

        return new ActionResult(ActionStatus::from($row->status), (string) $row->message_key);
    }

    public function record(string $operationId, CalendarContext $context, ActionResult $result): void
    {
        try {
            DB::table('demo_action_journal')->insert([
                'operation_id' => $operationId, 'scope_key' => $context->scopeKey, 'actor_id' => $context->actorId,
                'status' => $result->status->value, 'message_key' => $result->messageKey,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (QueryException) {
            // unique (scope, operation): a concurrent duplicate already recorded the outcome.
        }
    }

    public function guaranteesExactlyOnce(): bool
    {
        return false;
    }
}
