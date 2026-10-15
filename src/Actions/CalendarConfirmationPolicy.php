<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Enums\CalendarAction;

/** Decides which operations need an explicit confirmation step. Cancelling never reaches the handler. */
final class CalendarConfirmationPolicy
{
    public function requiresConfirmation(CalendarDefinition $definition, CalendarAction $action): bool
    {
        return match ($action) {
            CalendarAction::Move, CalendarAction::Resize => $definition->settings->feature('confirmMove'),
            CalendarAction::Delete, CalendarAction::SeriesDelete, CalendarAction::OccurrenceDelete => $definition->settings->feature('confirmDelete'),
            default => false,
        };
    }
}
