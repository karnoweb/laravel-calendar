<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

use Karnoweb\Calendar\Data\CalendarContext;

/**
 * Host-owned. The package never runs OAuth, stores tokens or registers routes.
 * Return a currently valid Google OAuth access token for the (server-resolved) actor, refreshing it
 * if needed, or null when the actor has not connected Google.
 */
interface GoogleAccessTokenResolver
{
    public function accessToken(CalendarContext $context): ?string;
}
