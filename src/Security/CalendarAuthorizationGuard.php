<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Security;

use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;

/** Single server-side authority for view/read/write decisions. Called for EVERY fetch/find/action. */
final class CalendarAuthorizationGuard
{
    public function assertCanRead(CalendarDefinition $definition, CalendarContext $context): void
    {
        if (! DefaultDenyPolicy::canRead($definition, $context)) {
            throw new CalendarAccessDenied;
        }
    }

    public function assertCanReadSource(CalendarDefinition $definition, CalendarContext $context, string $sourceKey): void
    {
        $this->assertCanRead($definition, $context);
        ContextScopeGuard::assertSource($definition, $sourceKey);
    }

    public function writeDenial(CalendarDefinition $definition, CalendarContext $context, CalendarAction $action, string $sourceKey): ?ActionStatus
    {
        return DefaultDenyPolicy::writeDenial($definition, $context, $action, $sourceKey);
    }

    public function canWriteAnything(CalendarDefinition $definition, CalendarContext $context): bool
    {
        if ($context->isAnonymous() || $definition->handlerClass === null) {
            return false;
        }
        foreach ($definition->sourceKeys() as $key) {
            if (DefaultDenyPolicy::writeDenial($definition, $context, CalendarAction::Create, $key) === null) {
                return true;
            }
        }

        return false;
    }
}
