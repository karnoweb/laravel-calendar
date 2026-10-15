<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Fixtures;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Contracts\ActionHandler;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\ActionResult;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use RuntimeException;

final class FakeHandler implements ActionHandler
{
    public function execute(ActionCommand $command, CalendarContext $context): ActionResult
    {
        FakeStore::$executed[] = $command->action->value.':'.$command->operationId;
        if (FakeStore::$throwOnExecute) {
            throw new RuntimeException('boom');
        }

        if ($command->action === CalendarAction::Create) {
            $id = (string) (count(FakeStore::$rows) + 1000);
            [$start, $end, $allDay] = $this->times($command->payload);
            FakeStore::$rows[$id] = [
                'id' => $id, 'scope' => $context->scopeKey, 'title' => (string) $command->payload['title'],
                'start' => $start, 'end' => $end, 'allDay' => $allDay, 'version' => '1', 'canEdit' => true, 'recurring' => false,
            ];

            return ActionResult::success();
        }

        $id = $command->reference?->eventId ?? '';
        $row = FakeStore::$rows[$id] ?? null;
        if ($row === null || $row['scope'] !== $context->scopeKey) {
            return ActionResult::failure(ActionStatus::NotFound);
        }
        if ($command->expectedVersion !== null && $command->expectedVersion !== $row['version']) {
            return ActionResult::failure(ActionStatus::Conflict);
        }

        switch ($command->action) {
            case CalendarAction::Delete:
                unset(FakeStore::$rows[$id]);
                break;
            case CalendarAction::Update:
                [$start, $end, $allDay] = $this->times($command->payload);
                $row['title'] = (string) $command->payload['title'];
                $row['start'] = $start;
                $row['end'] = $end;
                $row['allDay'] = $allDay;
                $row['version'] = (string) ((int) $row['version'] + 1);
                FakeStore::$rows[$id] = $row;
                break;
            case CalendarAction::Move:
                [$start, $end, $allDay] = $this->times($command->payload);
                $row['start'] = $start;
                $row['end'] = $end;
                $row['version'] = (string) ((int) $row['version'] + 1);
                FakeStore::$rows[$id] = $row;
                break;
            case CalendarAction::Resize:
                $row['end'] = (new DateTimeImmutable((string) $command->payload['end']))->setTimezone($row['end']->getTimezone());
                $row['version'] = (string) ((int) $row['version'] + 1);
                FakeStore::$rows[$id] = $row;
                break;
            default:
                return ActionResult::failure(ActionStatus::Unsupported);
        }

        return ActionResult::success();
    }

    /** @param array<string, mixed> $p @return array{0:DateTimeImmutable,1:DateTimeImmutable,2:bool} */
    private function times(array $p): array
    {
        $allDay = (bool) ($p['allDay'] ?? false);
        $tz = new DateTimeZone((string) ($p['timezone'] ?? 'UTC'));
        if ($allDay) {
            return [new DateTimeImmutable($p['start'].' 00:00:00', $tz), new DateTimeImmutable($p['end'].' 00:00:00', $tz), true];
        }

        return [new DateTimeImmutable((string) $p['start']), new DateTimeImmutable((string) $p['end']), false];
    }
}
