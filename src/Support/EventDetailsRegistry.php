<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use InvalidArgumentException;
use Karnoweb\Calendar\Contracts\EventDetailsSection;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;

/** Host-owned, source-specific related information; never stores domain data. */
final class EventDetailsRegistry
{
    /** @var array<string, array<string, array{label:string, provider:class-string<EventDetailsSection>}>> */
    private array $sections = [];

    /** @param class-string<EventDetailsSection> $provider */
    public function register(string $source, string $key, string $label, string $provider): void
    {
        if (! preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $key) || ! is_subclass_of($provider, EventDetailsSection::class)) {
            throw new InvalidArgumentException('Invalid details section registration.');
        }
        $this->sections[$source][$key] = ['label' => $label, 'provider' => $provider];
    }

    /** @param array<string, int> $pages
     * @return list<array<string, mixed>>
     */
    public function forEvent(CalendarEventData $event, CalendarContext $context, array $pages): array
    {
        $out = [];
        foreach ($this->sections[$event->reference->sourceKey] ?? [] as $key => $registration) {
            $provider = app($registration['provider']);
            if (! $provider->authorize($event, $context)) {
                continue;
            }
            $page = isset($pages[$key]) ? max(1, min(100, $pages[$key])) : null;
            $out[] = ['key' => $key, 'label' => $registration['label'], 'page' => $page,
                'data' => $page === null ? null : $provider->page($event, $context, $page, 20)];
        }

        return $out;
    }
}
