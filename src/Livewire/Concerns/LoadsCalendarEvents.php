<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire\Concerns;

use Karnoweb\Calendar\Actions\ReadDispatcher;
use Karnoweb\Calendar\Calendar\CalendarEngine;
use Karnoweb\Calendar\Calendar\DateLabelFormatter;
use Karnoweb\Calendar\Calendar\EventLayoutEngine;
use Karnoweb\Calendar\Calendar\IsoDate;
use Karnoweb\Calendar\Contracts\CalendarFormResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\CalendarNavigationState;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\ReadRequest;
use Karnoweb\Calendar\Data\ReadResult;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\CalendarView;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Exceptions\CalendarNotFound;
use Karnoweb\Calendar\Exceptions\CalendarRateLimited;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDefinition;
use Karnoweb\Calendar\Security\AllowedCssToken;
use Karnoweb\Calendar\Security\CalendarAuthorizationGuard;
use Karnoweb\Calendar\Security\CalendarInputNormalizer;
use Karnoweb\Calendar\Security\EventReferenceGuard;
use Karnoweb\Calendar\Support\CalendarFilterEngine;
use Karnoweb\Calendar\Support\CalendarTranslations;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Support\FilterValidator;
use Karnoweb\Calendar\Support\ResolvedCalendar;
use Karnoweb\Calendar\Support\ResultWindowManager;
use Throwable;

/** Builds the render model: authorised events for exactly the visible range, laid out per view. */
trait LoadsCalendarEvents
{
    /** @return array<string, mixed> */
    protected function buildViewModel(): array
    {
        try {
            $resolved = $this->resolved();
        } catch (CalendarAccessDenied) {
            return ['status' => 'denied'];
        } catch (InvalidCalendarDefinition $e) {
            report($e);

            return ['status' => 'error'];
        }
        if ($this->accessDenied) {
            return ['status' => 'denied'];
        }

        try {
            return $this->composeModel($resolved);
        } catch (CalendarRateLimited) {
            return ['status' => 'rate_limited'];
        } catch (CalendarAccessDenied) {
            return ['status' => 'denied'];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'error'];
        }
    }

    /** @return array<string, mixed> */
    private function composeModel(ResolvedCalendar $resolved): array
    {
        $def = $resolved->definition;
        $ctx = $resolved->context;
        $settings = $def->settings;
        $engine = app(CalendarEngine::class);
        $labels = app(DateLabelFormatter::class);
        $filterEngine = app(CalendarFilterEngine::class);
        $layout = app(EventLayoutEngine::class);
        $tz = $ctx->timezone;
        $locale = $ctx->locale;

        $state = $this->navState($resolved);
        $system = $engine->system($state);
        $range = $engine->range($state, $settings);

        $disabled = $filterEngine->clean($def, $this->disabledSources);
        $enabled = $filterEngine->enabledSources($def, $disabled);
        $activeFilters = FilterValidator::sanitize($def, array_merge($this->filters, (array) ($this->embed['presetFilters'] ?? [])));
        $search = $settings->feature('search') ? $this->search : '';

        $read = $enabled === []
            ? new ReadResult([], false, false, [], 'none')
            : app(ReadDispatcher::class)->read($def, $ctx, new ReadRequest($range, $search, $activeFilters, $enabled, $this->pages));

        $guard = app(CalendarAuthorizationGuard::class);
        $cards = fn (array $events, string $day) => array_map(fn (CalendarEventData $e) => $this->card($e, $def, $ctx, $guard, $day), $events);

        $model = [
            'status' => 'ok',
            'definition' => $def,
            'views' => $def->views->all(),
            'viewName' => $state->view->value, // not "view": that is a public component property and would shadow it
            'systemName' => $state->system->value,
            'locale' => $locale,
            'tz' => $tz,
            'dir' => CalendarTranslations::direction($settings->styles['direction'], $locale),
            'theme' => $settings->styles['theme'],
            'extraClass' => $settings->styles['class'],
            'poll' => (int) config('calendar.refresh.poll_seconds', 0),
            'today' => $engine->today($state),
            'cursorIso' => $state->date,
            'features' => $settings->features,
            'compact' => ($this->embed['layout'] ?? 'full') === 'compact',
            'enabledViews' => array_map(fn ($v) => $v->value, $settings->enabledViews),
            'enabledSystems' => array_map(fn ($s) => $s->value, $settings->enabledSystems),
            'read' => $read,
            'failedSources' => array_map(fn (string $k) => $def->source($k)->label, $read->failedSources()),
            'search' => $this->search,
            'sources' => $this->sourceModels($def, $disabled, $read),
            'filtersUi' => $this->filterModels($def, $activeFilters),
            'hasActiveFilters' => $activeFilters !== [] || $disabled !== [] || $search !== '',
            'canCreate' => $settings->feature('create') && $guard->canWriteAnything($def, $ctx),
            'writableSources' => $this->writableSources($def, $ctx, $guard),
            'monthLimit' => ($this->embed['layout'] ?? 'full') === 'compact' ? min(2, $settings->limit('monthCellLimit')) : $settings->limit('monthCellLimit'),
            'weekdayNames' => [],
            'hours' => range(0, 23),
            'labels' => $labels,
            'pages' => $this->pages,
        ];

        [$cy, $cm] = $system->fromIso($state->date);
        $model['pickerYear'] = $cy;
        $model['pickerMonth'] = $cm;
        $model['monthNames'] = array_combine(range(1, 12), array_map(fn (int $m) => $labels->monthName($state->system, $m, $locale), range(1, 12)));
        $model['title'] = $this->title($state, $engine, $labels, $locale, $range, $system);
        $model['digits'] = fn (string|int $v) => $labels->digits($v, $locale);
        $model['dayLabel'] = fn (string $iso) => $labels->dayTitle($system, $iso, $locale);
        $model['weekendDays'] = $system->weekendDays();
        $altSystem = app(\Karnoweb\Calendar\Calendar\CalendarSystemRegistry::class)->get($state->system === \Karnoweb\Calendar\Enums\CalendarSystem::Jalali ? \Karnoweb\Calendar\Enums\CalendarSystem::Gregorian : \Karnoweb\Calendar\Enums\CalendarSystem::Jalali);
        $altFa = $locale === 'fa' && $altSystem->system() === \Karnoweb\Calendar\Enums\CalendarSystem::Jalali;
        $model['altDay'] = fn (string $iso) => $altFa ? $labels->digits($altSystem->fromIso($iso)[2], $locale) : (string) $altSystem->fromIso($iso)[2];

        $firstWeekday = $engine->firstWeekday($state, $settings);
        $model['weekdayOrder'] = array_map(fn (int $i) => ($firstWeekday + $i) % 7, range(0, 6));
        $model['weekdayNames'] = array_map(fn (int $wd) => ['long' => $labels->weekdayName($wd, $locale), 'short' => $labels->weekdayName($wd, $locale, true), 'n' => $wd], $model['weekdayOrder']);

        match ($state->view) {
            CalendarView::Month => $this->composeMonth($model, $engine, $layout, $state, $settings, $read, $cards),
            CalendarView::Week => $this->composeWeek($model, $engine, $layout, $state, $settings, $read, $cards),
            CalendarView::Day => $this->composeDay($model, $engine, $layout, $state, $read, $cards),
            CalendarView::List => $this->composeList($model, $def, $system, $tz, $locale, $read),
        };

        $model += $this->composeDialogs($resolved, $state, $engine, $labels, $system, $enabled, $activeFilters, $search, $cards);

        return $model;
    }

    /** @param array<string, mixed> $model */
    private function composeMonth(array &$model, CalendarEngine $engine, EventLayoutEngine $layout, CalendarNavigationState $state, $settings, ReadResult $read, callable $cards): void
    {
        $grid = $engine->monthGrid($state, $settings);
        $days = array_map(fn ($d) => $d->iso, $grid->days());
        $byDay = $layout->byDay($read->events, $days, $state->timezone);
        $rows = [];
        foreach ($grid->rows as $row) {
            $rows[] = array_map(fn ($day) => ['day' => $day, 'cards' => $cards($byDay[$day->iso] ?? [], $day->iso), 'count' => count($byDay[$day->iso] ?? [])], $row);
        }
        $model['monthRows'] = $rows;
    }

    /** @param array<string, mixed> $model */
    private function composeWeek(array &$model, CalendarEngine $engine, EventLayoutEngine $layout, CalendarNavigationState $state, $settings, ReadResult $read, callable $cards): void
    {
        $grid = $engine->weekGrid($state, $settings);
        $model['columns'] = $this->columns($grid->days, $layout, $state->timezone, $read, $cards);
    }

    /** @param array<string, mixed> $model */
    private function composeDay(array &$model, CalendarEngine $engine, EventLayoutEngine $layout, CalendarNavigationState $state, ReadResult $read, callable $cards): void
    {
        $grid = $engine->dayGrid($state);
        $model['columns'] = $this->columns([$grid->day], $layout, $state->timezone, $read, $cards);
    }

    /**
     * @param  list<\Karnoweb\Calendar\Data\CalendarDayData>  $days
     * @return list<array<string, mixed>>
     */
    private function columns(array $days, EventLayoutEngine $layout, string $tz, ReadResult $read, callable $cards): array
    {
        $isoDays = array_map(fn ($d) => $d->iso, $days);
        $byDay = $layout->byDay($read->events, $isoDays, $tz);
        $columns = [];
        foreach ($days as $day) {
            $events = $byDay[$day->iso] ?? [];
            $allDay = array_values(array_filter($events, fn (CalendarEventData $e) => $e->allDay));
            $timed = [];
            foreach ($layout->timeLayout($events, $day->iso, $tz) as $item) {
                $card = ($cards([$item['event']], $day->iso))[0];
                $timed[] = $card + [
                    'top' => round($item['startMinute'] / 1440 * 100, 4),
                    'height' => max(1.4, round(($item['endMinute'] - $item['startMinute']) / 1440 * 100, 4)),
                    'left' => round($item['lane'] / $item['lanes'] * 100, 4),
                    'width' => round(100 / $item['lanes'], 4),
                    'continuesBefore' => $item['continuesBefore'],
                    'continuesAfter' => $item['continuesAfter'],
                ];
            }
            $columns[] = ['day' => $day, 'allDay' => $cards($allDay, $day->iso), 'timed' => $timed];
        }

        return $columns;
    }

    /** @param array<string, mixed> $model */
    private function composeList(array &$model, CalendarDefinition $def, $system, string $tz, string $locale, ReadResult $read): void
    {
        $rows = app(ResultWindowManager::class)->rows($read->events, $def, $system, $tz, $locale);
        $model['listRowsJson'] = json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $model['listRowCount'] = count($rows);
        $model['listRowHeight'] = ResultWindowManager::ROW_HEIGHT;
        $model['listLoaded'] = count($read->events);
    }

    /**
     * Drawer (day events), details drawer and form schema.
     *
     * @param  list<string>  $enabled
     * @param  array<string, string>  $activeFilters
     * @return array<string, mixed>
     */
    private function composeDialogs(ResolvedCalendar $resolved, CalendarNavigationState $state, CalendarEngine $engine, DateLabelFormatter $labels, $system, array $enabled, array $activeFilters, string $search, callable $cards): array
    {
        $def = $resolved->definition;
        $ctx = $resolved->context;
        $out = ['drawer' => null, 'details' => null, 'formSchema' => null, 'pendingView' => $this->pending, 'scopePromptView' => $this->scopePrompt];

        if ($this->drawerDate !== null) {
            $iso = CalendarInputNormalizer::tryGregorianDate($this->drawerDate);
            if ($iso === null) {
                $this->drawerDate = null;
            } else {
                $range = DateRange::ofLocalDays($iso, IsoDate::addDays($iso, 1), $ctx->timezone);
                $result = app(ReadDispatcher::class)->read($def, $ctx, new ReadRequest($range, $search, $activeFilters, $enabled, $this->drawerPages));
                $out['drawer'] = [
                    'iso' => $iso,
                    'title' => $labels->dayTitle($system, $iso, $ctx->locale),
                    'cards' => $cards($result->events, $iso),
                    'hasMore' => $result->hasMore,
                    'count' => count($result->events),
                ];
            }
        }

        if ($this->detailsRef !== null) {
            $out['details'] = $this->detailsModel($resolved, $labels, $system);
        }

        if ($this->formOpen && $this->formSource !== '' && $def->hasSource($this->formSource)) {
            $out['formSchema'] = app(CalendarFormResolver::class)->schema($def, $ctx, $this->formSource, null);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function detailsModel(ResolvedCalendar $resolved, DateLabelFormatter $labels, $system): ?array
    {
        $ref = EventReferenceCodec::tryDecode((string) $this->detailsRef);
        if ($ref === null) {
            $this->detailsRef = null;

            return null;
        }
        $def = $resolved->definition;
        $ctx = $resolved->context;
        try {
            $event = app(EventReferenceGuard::class)->resolve($def, $ctx, $ref);
        } catch (CalendarAccessDenied|CalendarNotFound) {
            $this->detailsRef = null;
            $this->notify('error', (string) trans('calendar::calendar.messages.not_found'));

            return null;
        }
        $guard = app(CalendarAuthorizationGuard::class);
        $card = $this->card($event, $def, $ctx, $guard, $event->firstDate($ctx->timezone));
        $tz = $ctx->timezone;
        $first = $event->firstDate($tz);
        $last = $event->lastDate($tz);
        $when = $event->allDay
            ? $labels->dayTitle($system, $first, $ctx->locale).($last > $first ? ' → '.$labels->dayTitle($system, $last, $ctx->locale) : '')
            : $labels->dayTitle($system, $first, $ctx->locale).' '.$labels->time($event->start, $tz, $ctx->locale).' – '.($last > $first ? $labels->dayTitle($system, $last, $ctx->locale).' ' : '').$labels->time($event->end, $tz, $ctx->locale);

        return $card + [
            'when' => $when,
            'canEdit' => $event->capabilities->canEdit && $guard->writeDenial($def, $ctx, CalendarAction::Update, $ref->sourceKey) === null,
            'canDelete' => $event->capabilities->canDelete && $guard->writeDenial($def, $ctx, CalendarAction::Delete, $ref->sourceKey) === null,
        ];
    }

    /** @return array<string, mixed> */
    private function card(CalendarEventData $event, CalendarDefinition $def, CalendarContext $ctx, CalendarAuthorizationGuard $guard, string $dayIso): array
    {
        $tz = $ctx->timezone;
        $labels = app(DateLabelFormatter::class);
        $source = $def->source($event->reference->sourceKey);
        $color = AllowedCssToken::color($event->color ?? $source->color);
        $first = $event->firstDate($tz);
        $last = $event->lastDate($tz);
        $position = $first === $last ? 'single' : ($dayIso === $first ? 'start' : ($dayIso === $last ? 'end' : 'middle'));

        $movable = $event->capabilities->canMove && $guard->writeDenial($def, $ctx, CalendarAction::Move, $event->reference->sourceKey) === null;
        $resizable = ! $event->allDay && $event->capabilities->canResize && $guard->writeDenial($def, $ctx, CalendarAction::Resize, $event->reference->sourceKey) === null;

        return [
            'event' => $event,
            'ref' => EventReferenceCodec::encode($event->reference),
            'colorClass' => AllowedCssToken::cssClass($color),
            'colorStyle' => AllowedCssToken::style($color),
            'source' => $source,
            'position' => $position,
            'timeLabel' => $event->allDay ? '' : $labels->time($event->start, $tz, $ctx->locale),
            'timeRange' => $event->allDay ? '' : $labels->time($event->start, $tz, $ctx->locale).' – '.$labels->time($event->end, $tz, $ctx->locale),
            'movable' => $movable,
            'resizable' => $resizable,
            'readOnly' => $event->isReadOnly() || (! $movable && ! $resizable && ! $event->capabilities->canEdit),
            'version' => $event->version,
            'locale' => $ctx->locale,
            'tz' => $tz,
            'dayIso' => $dayIso,
        ];
    }

    /**
     * @param  list<string>  $disabled
     * @return list<array<string, mixed>>
     */
    private function sourceModels(CalendarDefinition $def, array $disabled, ReadResult $read): array
    {
        $out = [];
        foreach ($def->sourceKeys() as $key) {
            $s = $def->source($key);
            if (! $s->visible) {
                continue;
            }
            $color = AllowedCssToken::color($s->color);
            $out[] = [
                'key' => $key, 'label' => $s->label, 'enabled' => ! in_array($key, $disabled, true), 'readOnly' => $s->readOnly,
                'colorClass' => AllowedCssToken::cssClass($color), 'colorStyle' => AllowedCssToken::style($color),
                'failed' => ($read->sources[$key]['error'] ?? null) !== null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $active
     * @return list<array<string, mixed>>
     */
    private function filterModels(CalendarDefinition $def, array $active): array
    {
        return array_map(fn ($f) => [
            'key' => $f->key(), 'label' => $f->label, 'options' => $f->options, 'selected' => $active[$f->key()] ?? '',
            'source' => $def->source($f->sourceKey)->label,
        ], $def->filters);
    }

    /** @return list<array{key:string,label:string}> */
    private function writableSources(CalendarDefinition $def, CalendarContext $ctx, CalendarAuthorizationGuard $guard): array
    {
        $out = [];
        foreach ($def->sourceKeys() as $key) {
            if ($guard->writeDenial($def, $ctx, CalendarAction::Create, $key) === null) {
                $out[] = ['key' => $key, 'label' => $def->source($key)->label];
            }
        }

        return $out;
    }

    private function title(CalendarNavigationState $state, CalendarEngine $engine, DateLabelFormatter $labels, string $locale, DateRange $range, $system): string
    {
        [$y, $m] = $system->fromIso($state->date);

        return match ($state->view) {
            CalendarView::Month, CalendarView::List => $labels->monthTitle($state->system, $y, $m, $locale),
            CalendarView::Day => $labels->dayTitle($system, $state->date, $locale),
            CalendarView::Week => (function () use ($range, $system, $labels, $locale, $state) {
                $first = $range->startInclusive->setTimezone(new \DateTimeZone($state->timezone))->format('Y-m-d');
                $last = IsoDate::addDays($first, 6);
                [$y1, $m1, $d1] = $system->fromIso($first);
                [$y2, $m2, $d2] = $system->fromIso($last);

                return $labels->digits($d1, $locale).' '.$labels->monthName($state->system, $m1, $locale).($y1 !== $y2 ? ' '.$labels->digits($y1, $locale) : '')
                    .' – '.$labels->digits($d2, $locale).' '.$labels->monthName($state->system, $m2, $locale).' '.$labels->digits($y2, $locale);
            })(),
        };
    }
}
