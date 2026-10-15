<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Enums\ActionStatus;

/** Translates results for the UI. "success" is produced ONLY from ActionStatus::Success. */
final class ActionResultPresenter
{
    /**
     * @return array{type:string, status:string, message:string, fieldErrors:array<string, string>}
     */
    public function present(ActionResult $result): array
    {
        $type = match ($result->status) {
            ActionStatus::Success => 'success',
            ActionStatus::Conflict, ActionStatus::UnknownOutcome, ActionStatus::RetryableError, ActionStatus::RateLimited => 'warning',
            default => 'error',
        };
        $key = $result->messageKey !== '' ? $result->messageKey : 'calendar::calendar.messages.'.strtolower($result->status->value);

        return [
            'type' => $type,
            'status' => $result->status->value,
            'message' => $this->translate($key),
            'fieldErrors' => $this->fieldErrors($result->fieldErrors),
        ];
    }

    /** @param array<string, list<string>|string> $errors @return array<string, string> */
    public function fieldErrors(array $errors): array
    {
        $out = [];
        foreach ($errors as $field => $message) {
            $first = is_array($message) ? (string) ($message[0] ?? '') : $message;
            $out[$field] = $this->translate($first);
        }

        return $out;
    }

    private function translate(string $key): string
    {
        $translated = trans($key);

        return is_string($translated) ? $translated : $key;
    }
}
