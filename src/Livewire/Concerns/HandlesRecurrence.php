<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire\Concerns;

use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Enums\RecurrenceScope;
use Karnoweb\Calendar\Support\ResolvedCalendar;

/** "This event / this and following / all events" for recurring occurrences. */
trait HandlesRecurrence
{
    public function chooseScope(string $scope): void
    {
        $chosen = RecurrenceScope::tryFrom($scope);
        $prompt = $this->scopePrompt;
        $this->scopePrompt = null;
        if ($chosen === null || $prompt === null) {
            return;
        }

        $this->guarded(function (ResolvedCalendar $r) use ($chosen, $prompt): void {
            $ref = (string) ($prompt['ref'] ?? '');
            $version = isset($prompt['version']) && is_string($prompt['version']) ? $prompt['version'] : null;
            $operationId = (string) ($prompt['operationId'] ?? '');
            $payload = is_array($prompt['payload'] ?? null) ? $prompt['payload'] : [];

            switch ($prompt['intent'] ?? '') {
                case 'edit':
                    $this->openEditForm($r, $ref, $chosen, $version);
                    break;
                case 'delete':
                    $action = $chosen->deleteAction();
                    if ($chosen === RecurrenceScope::Future) {
                        $payload = ['mode' => 'delete'];
                    }
                    $this->stage($r, $action, $ref, $payload, $version, $operationId, $chosen);
                    break;
                case 'move':
                case 'resize':
                    // The command stays move/resize; the chosen scope (this/future/all) travels in ActionCommand::$scope
                    // and the payload already carries the final instants. The host decides override vs split vs series shift.
                    $this->stage($r, $this->moveActionFor($prompt), $ref, $payload, $version, $operationId, $chosen);
                    break;
            }
        });
    }

    public function cancelScope(): void
    {
        $this->cancelPending();
    }

    /** @param array<string, mixed> $prompt */
    private function moveActionFor(array $prompt): CalendarAction
    {
        return ($prompt['intent'] ?? '') === 'resize' ? CalendarAction::Resize : CalendarAction::Move;
    }
}
