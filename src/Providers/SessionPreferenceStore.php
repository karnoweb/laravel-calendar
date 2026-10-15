<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Providers;

use Illuminate\Contracts\Session\Session;
use Karnoweb\Calendar\Contracts\PreferenceStore;
use Karnoweb\Calendar\Data\CalendarContext;

/** Keeps view/filter preferences in the user's own session; scoped by calendar + scope key. */
final class SessionPreferenceStore implements PreferenceStore
{
    public function __construct(private readonly Session $session) {}

    public function get(CalendarContext $context): array
    {
        $value = $this->session->get($this->key($context), []);

        return is_array($value) ? $value : [];
    }

    public function put(CalendarContext $context, array $preferences): void
    {
        $this->session->put($this->key($context), $preferences);
    }

    private function key(CalendarContext $context): string
    {
        return 'calendar.prefs.'.hash('sha256', $context->calendarKey.'|'.$context->scopeKey);
    }
}
