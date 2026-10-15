<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

use Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver;
use Karnoweb\Calendar\Data\CalendarContext;

/**
 * Host decision for the demo, driven by the server-side session set on /workbench/login/...?google=fake|live:
 *   fake : requests go to an in-process stand-in for the Google API (no credentials needed)
 *   live : the access token comes from env WORKBENCH_GOOGLE_TOKEN
 * A real host would read the signed-in user's stored (and refreshed) Google OAuth token here.
 */
final class DemoGoogleTokenResolver implements GoogleAccessTokenResolver
{
    public function accessToken(CalendarContext $context): ?string
    {
        if ($context->actorId === null) {
            return null;
        }

        return match (session('demo_google')) {
            'fake' => $this->fake(),
            'live' => env('WORKBENCH_GOOGLE_TOKEN') ?: null,
            default => null,
        };
    }

    private function fake(): string
    {
        config(['calendar.google.writable' => true]);
        FakeGoogleApi::install();

        return 'fake-token';
    }
}
