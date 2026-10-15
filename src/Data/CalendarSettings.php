<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Enums\CalendarView;
use Karnoweb\Calendar\Security\AllowedCssToken;

final readonly class CalendarSettings
{
    public const FEATURES = [
        'create' => true, 'edit' => true, 'delete' => true, 'move' => true, 'resize' => true,
        'recurrence' => true, 'search' => true, 'filters' => true, 'systemToggle' => true,
        'viewSwitcher' => true, 'confirmMove' => true, 'confirmDelete' => true,
    ];

    public const LIMITS = [
        'maxRangeDays' => 366, 'maxEventsPerResponse' => 250, 'maxOccurrences' => 500,
        'maxSearchLength' => 120, 'monthCellLimit' => 3, 'maxPages' => 60, 'maxPayloadBytes' => 32768,
    ];

    public const TIMEZONE_POLICIES = ['context', 'app'];

    /** @var array<string, bool> */
    public array $features;

    /** @var array<string, int> */
    public array $limits;

    /** @var array{theme: string, direction: string, class: string} */
    public array $styles;

    public int $maxEventsPerResponse;

    /** @var list<CalendarView> */
    public array $enabledViews;

    /** @var list<CalendarSystem> */
    public array $enabledSystems;

    /**
     * @param  CalendarSystem|null  $calendarSystem  null = derived from locale (fa => jalali)
     * @param  array<string, bool>  $features
     * @param  array<string, int>  $limits
     * @param  array<string, string>  $styles
     * @param  bool  $requireOneSource  when true the last enabled source can never be switched off
     */
    public function __construct(
        public CalendarView $defaultView = CalendarView::Month,
        public ?CalendarSystem $calendarSystem = null,
        int $maxEventsPerResponse = 250,
        public ?int $firstWeekday = null,
        public string $timezonePolicy = 'context',
        array $features = [],
        array $limits = [],
        array $styles = [],
        public bool $urlSync = true,
        public bool $allowAnonymousRead = false,
        public bool $requireOneSource = false,
        array $enabledViews = [],
        array $enabledSystems = [],
    ) {
        $views = [];
        foreach ($enabledViews === [] ? CalendarView::cases() : $enabledViews as $v) {
            $v = $v instanceof CalendarView ? $v : CalendarView::tryFrom((string) $v);
            if ($v === null) {
                throw new InvalidArgumentException('Unknown calendar view.');
            }
            $views[$v->value] = $v;
        }
        $systems = [];
        foreach ($enabledSystems === [] ? CalendarSystem::cases() : $enabledSystems as $s) {
            $s = $s instanceof CalendarSystem ? $s : CalendarSystem::tryFrom((string) $s);
            if ($s === null) {
                throw new InvalidArgumentException('Unknown calendar system.');
            }
            $systems[$s->value] = $s;
        }
        if (! isset($views[$defaultView->value])) {
            throw new InvalidArgumentException('defaultView must be one of the enabled views.');
        }
        if ($calendarSystem !== null && ! isset($systems[$calendarSystem->value])) {
            throw new InvalidArgumentException('calendarSystem must be one of the enabled systems.');
        }
        $this->enabledViews = array_values($views);
        $this->enabledSystems = array_values($systems);
        if ($maxEventsPerResponse < 1 || $maxEventsPerResponse > 1000) {
            throw new InvalidArgumentException('maxEventsPerResponse out of range.');
        }
        if ($firstWeekday !== null && ($firstWeekday < 0 || $firstWeekday > 6)) {
            throw new InvalidArgumentException('firstWeekday must be 0..6.');
        }
        if (! in_array($timezonePolicy, self::TIMEZONE_POLICIES, true)) {
            throw new InvalidArgumentException('Invalid timezone policy.');
        }

        foreach ($features as $name => $enabled) {
            if (! array_key_exists($name, self::FEATURES) || ! is_bool($enabled)) {
                throw new InvalidArgumentException("Unknown or invalid calendar feature [{$name}].");
            }
        }
        foreach ($limits as $name => $value) {
            if (! array_key_exists($name, self::LIMITS) || ! is_int($value) || $value < 1) {
                throw new InvalidArgumentException("Unknown or invalid calendar limit [{$name}].");
            }
        }
        if (isset($limits['maxEventsPerResponse'])) {
            $maxEventsPerResponse = min(1000, $limits['maxEventsPerResponse']);
        }

        $this->maxEventsPerResponse = $maxEventsPerResponse;
        $this->features = array_replace(self::FEATURES, $features);
        $this->limits = array_replace(self::LIMITS, $limits, ['maxEventsPerResponse' => $maxEventsPerResponse]);
        $this->styles = [
            'theme' => AllowedCssToken::theme($styles['theme'] ?? 'auto'),
            'direction' => AllowedCssToken::direction($styles['direction'] ?? 'auto'),
            'class' => preg_match('/^[a-z0-9_ -]{0,80}$/i', $styles['class'] ?? '') ? ($styles['class'] ?? '') : '',
        ];
    }

    public function viewEnabled(CalendarView $view): bool
    {
        return in_array($view, $this->enabledViews, true);
    }

    public function systemEnabled(CalendarSystem $system): bool
    {
        return in_array($system, $this->enabledSystems, true);
    }

    /**
     * Narrow (never widen) the settings for one embedded instance.
     *
     * @param  list<CalendarView>|null  $views
     * @param  list<CalendarSystem>|null  $systems
     */
    public function restrict(?array $views = null, ?array $systems = null, ?CalendarView $defaultView = null, ?CalendarSystem $system = null): self
    {
        $v = $views === null ? $this->enabledViews : array_values(array_filter($views, fn (CalendarView $x) => $this->viewEnabled($x)));
        $s = $systems === null ? $this->enabledSystems : array_values(array_filter($systems, fn (CalendarSystem $x) => $this->systemEnabled($x)));
        if ($v === [] || $s === []) {
            throw new InvalidArgumentException('Embed restrictions leave no enabled view or system.');
        }
        $default = $defaultView !== null && in_array($defaultView, $v, true) ? $defaultView : (in_array($this->defaultView, $v, true) ? $this->defaultView : $v[0]);
        $chosen = $system !== null && in_array($system, $s, true) ? $system : $this->calendarSystem;
        if ($chosen !== null && ! in_array($chosen, $s, true)) {
            $chosen = $s[0];
        }
        if (count($s) === 1) {
            $chosen = $s[0];
        }

        return new self($default, $chosen, $this->maxEventsPerResponse, $this->firstWeekday, $this->timezonePolicy, $this->features, $this->limits, $this->styles, $this->urlSync, $this->allowAnonymousRead, $this->requireOneSource, $v, $s);
    }

    public function feature(string $name): bool
    {
        return $this->features[$name] ?? false;
    }

    public function limit(string $name): int
    {
        return $this->limits[$name] ?? self::LIMITS[$name];
    }
}
