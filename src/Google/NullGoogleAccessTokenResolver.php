<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Google;

use Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver;
use Karnoweb\Calendar\Data\CalendarContext;

/** Default: Google is not connected. The host binds its own resolver to enable the integration. */
final class NullGoogleAccessTokenResolver implements GoogleAccessTokenResolver
{
    public function accessToken(CalendarContext $context): ?string
    {
        return null;
    }
}
