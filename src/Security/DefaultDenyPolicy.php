<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Security;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;

/**
 * Package-level fail-closed decisions. The host handler/provider still make the final decision;
 * this policy only ever NARROWS access.
 */
final class DefaultDenyPolicy
{
    public static function canRead(CalendarDefinition $definition, CalendarContext $context): bool
    {
        if ($context->calendarKey !== $definition->key) {
            return false;
        }
        if ($context->isAnonymous()) {
            return $definition->settings->allowAnonymousRead && (bool) config('calendar.security.allow_public_calendars', true);
        }

        return true;
    }

    /** @return ActionStatus|null null when the action may proceed to the host handler */
    public static function writeDenial(CalendarDefinition $definition, CalendarContext $context, CalendarAction $action, string $sourceKey): ?ActionStatus
    {
        if (! self::canRead($definition, $context) || $context->isAnonymous()) {
            return ActionStatus::Forbidden;
        }
        if (! $definition->hasSource($sourceKey)) {
            return ActionStatus::Forbidden;
        }
        if ($definition->handlerClass === null) {
            return ActionStatus::ReadOnly;
        }
        if (! $definition->settings->feature($action->feature())) {
            return ActionStatus::Unsupported;
        }
        if ($action->isRecurrenceAction() && ! $definition->settings->feature('recurrence')) {
            return ActionStatus::Unsupported;
        }
        $source = $definition->source($sourceKey);
        if ($source->readOnly || ! $source->capabilities->allows($action->capability())) {
            return ActionStatus::ReadOnly;
        }

        return null;
    }
}
