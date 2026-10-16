<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use Illuminate\Contracts\Container\Container;
use Karnoweb\Calendar\Cache\CalendarCacheKey;
use Karnoweb\Calendar\Contracts\CalendarCache;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\ReadRequest;
use Karnoweb\Calendar\Data\ReadResult;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Exceptions\CalendarRateLimited;
use Karnoweb\Calendar\Observability\CalendarTelemetry;
use Karnoweb\Calendar\Security\CalendarAuthorizationGuard;
use Karnoweb\Calendar\Security\CalendarRateLimitGuard;
use Karnoweb\Calendar\Support\CalendarLoadBudget;
use Karnoweb\Calendar\Support\CursorPaginationMerger;
use Karnoweb\Calendar\Support\FilterValidator;
use Karnoweb\Calendar\Support\QuerySignature;
use Karnoweb\Calendar\Support\SourceAggregator;
use Karnoweb\Calendar\Support\SourceRegistry;
use Throwable;

/**
 * Collects events from several independent providers. Every call re-checks read authorization;
 * a failing/denied provider degrades only its own source and never leaks into the others.
 */
final class ReadDispatcher
{
    public function __construct(
        private readonly Container $container,
        private readonly CalendarAuthorizationGuard $guard,
        private readonly CalendarRateLimitGuard $limits,
        private readonly CalendarCache $cache,
        private readonly CalendarTelemetry $telemetry,
    ) {}

    /** @throws CalendarAccessDenied|CalendarRateLimited */
    public function read(CalendarDefinition $definition, CalendarContext $context, ReadRequest $request): ReadResult
    {
        $this->guard->assertCanRead($definition, $context);
        $this->limits->hit($context, 'read');
        $this->limits->assertRange($definition, $request->range);

        return $this->telemetry->measure('read', fn () => $this->collect($definition, $context, $request), [
            'calendar' => $context->calendarKey,
            'sources' => count($definition->sources),
        ]);
    }

    private function collect(CalendarDefinition $definition, CalendarContext $context, ReadRequest $request): ReadResult
    {
        $settings = $definition->settings;
        $search = $this->limits->assertSearch($definition, $request->search);
        $filters = FilterValidator::sanitize($definition, $request->filters);
        $requestKey = $request->requestKey !== '' ? $request->requestKey : QuerySignature::for($definition->key, $request);
        $registry = new SourceRegistry($definition, $this->container);

        // Only declared sources can be selected; unknown keys from the client are ignored.
        $wanted = $request->sources === null
            ? $definition->sourceKeys()
            : array_values(array_intersect($definition->sourceKeys(), $request->sources));

        $pages = max(1, min($request->pages, $settings->limit('maxPages')));
        $budget = CalendarLoadBudget::for($definition, $pages);
        $cursors = $request->cursor !== null ? CursorPaginationMerger::decode($request->cursor) : null;

        $bySource = [];
        $summary = [];
        $nextCursors = [];
        $truncated = false;
        $version = $this->cache->scopeVersion($definition->key, $context->scopeKey);

        foreach ($wanted as $sourceKey) {
            if ($cursors !== null && ! isset($cursors[$sourceKey])) {
                continue; // exhausted in an earlier page of the composite cursor
            }
            $events = [];
            $hasMore = false;
            $error = null;
            try {
                $provider = $registry->provider($sourceKey);
                $cursor = $cursors[$sourceKey] ?? null;
                $loop = $cursors !== null ? 1 : $pages;
                for ($i = 0; $i < $loop; $i++) {
                    $budget->chargeQuery();
                    $query = new EventQuery(
                        $request->range,
                        $settings->maxEventsPerResponse,
                        $cursor,
                        $search,
                        FilterValidator::forSource($sourceKey, $filters),
                        $requestKey,
                    );
                    $page = $this->fetchPage($provider, $query, $context, $definition, $sourceKey, $version, $wanted);
                    $pageEvents = $page->events;
                    if (count($pageEvents) > $settings->maxEventsPerResponse) {
                        $pageEvents = array_slice($pageEvents, 0, $settings->maxEventsPerResponse);
                        $truncated = true;
                    }
                    $events = [...$events, ...$pageEvents];
                    $truncated = $truncated || $page->truncated;
                    $hasMore = $page->hasMore;
                    $cursor = $page->nextCursor;
                    if (! $page->hasMore || ! $budget->chargeEvents(count($page->events))) {
                        $hasMore = $hasMore && $page->hasMore;
                        break;
                    }
                }
                if ($hasMore && $cursor !== null) {
                    $nextCursors[$sourceKey] = $cursor;
                }
            } catch (CalendarRateLimited $e) {
                throw $e;
            } catch (CalendarAccessDenied) {
                $error = 'denied';
                $events = [];
            } catch (Throwable $e) {
                $error = 'failed';
                $events = [];
                $this->telemetry->record('read.source_failed', [
                    'calendar' => $context->calendarKey, 'source' => $sourceKey, 'exception' => $e::class,
                ]);
            }
            $bySource[$sourceKey] = $events;
            $summary[$sourceKey] = ['count' => count($events), 'hasMore' => $hasMore, 'error' => $error];
        }

        $merged = SourceAggregator::merge($bySource, $request->range, $context->timezone);
        $anyMore = in_array(true, array_column($summary, 'hasMore'), true);

        return new ReadResult($merged, $anyMore, $truncated, $summary, $requestKey, CursorPaginationMerger::encode($nextCursors));
    }

    /** @param list<string> $selectedSources */
    private function fetchPage(
        object $provider,
        EventQuery $query,
        CalendarContext $context,
        CalendarDefinition $definition,
        string $sourceKey,
        string $version,
        array $selectedSources = [],
    ): EventPage {
        $fetch = fn (): EventPage => $provider->fetch($query, $context);
        if (! $this->cache->enabled()) {
            return $fetch();
        }
        $key = CalendarCacheKey::for($context, $sourceKey, $query, $version, $selectedSources);

        $page = $this->cache->remember($key, (int) config('calendar.cache.ttl', 60), $fetch);

        return $page instanceof EventPage ? $page : $fetch();
    }

    /**
     * Single source / single day helper for drawers. Same guarantees as read().
     *
     * @return list<CalendarEventData>
     */
    public function eventsFor(CalendarDefinition $definition, CalendarContext $context, ReadRequest $request): array
    {
        return $this->read($definition, $context, $request)->events;
    }
}
