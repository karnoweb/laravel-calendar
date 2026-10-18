<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Livewire;

use Illuminate\Contracts\View\View;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Livewire\Concerns\HandlesCalendarActions;
use Karnoweb\Calendar\Livewire\Concerns\HandlesCalendarForms;
use Karnoweb\Calendar\Livewire\Concerns\HandlesRecurrence;
use Karnoweb\Calendar\Livewire\Concerns\HasCalendarFilters;
use Karnoweb\Calendar\Livewire\Concerns\HasNavigation;
use Karnoweb\Calendar\Livewire\Concerns\HasViewSwitcher;
use Karnoweb\Calendar\Livewire\Concerns\LoadsCalendarEvents;
use Karnoweb\Calendar\Support\CalendarRuntime;
use Karnoweb\Calendar\Support\EmbedOptions;
use Karnoweb\Calendar\Support\ResolvedCalendar;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The calendar UI. State held here is ONLY presentation state (view/date/filters/open dialogs).
 * Definitions, contexts, providers and handlers are rebuilt from the host container on every
 * request and never stored in component state.
 */
class Calendar extends Component
{
    use HandlesCalendarActions;
    use HandlesCalendarForms;
    use HandlesRecurrence;
    use HasCalendarFilters;
    use HasNavigation;
    use HasViewSwitcher;
    use LoadsCalendarEvents;

    #[Locked]
    public string $calendarKey = '';

    #[Locked]
    public bool $urlSync = true;

    #[Locked]
    public string $urlPrefix = '';

    /** Host-written placement options (see EmbedOptions); locked so the browser can never widen them. @var array<string, mixed> */
    #[Locked]
    public array $embed = [];

    // --- navigation (display-system values; strings so malformed URLs can never raise a TypeError) ---
    public string $view = '';

    public string $system = '';

    public string $year = '';

    public string $month = '';

    public string $day = '';

    // --- filters ---
    public string $search = '';

    /** @var list<string> */
    public array $disabledSources = [];

    /** @var array<string, string> */
    public array $filters = [];

    // --- paging (counts only; cursors never come from the browser) ---
    public int $pages = 1;

    public int $drawerPages = 1;

    // --- dialogs ---
    public ?string $drawerDate = null;

    public ?string $detailsRef = null;

    /** @var array<string, int> */
    #[Locked]
    public array $detailsSectionPages = [];

    public function detailsSectionPage(string $key, int $page = 1): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $key)) {
            $this->detailsSectionPages[$key] = max(1, min(100, $page));
        }
    }

    public bool $formOpen = false;

    public string $formMode = 'create';

    public ?string $formRef = null;

    public string $formSource = '';

    public ?string $formScope = null;

    public ?string $formVersion = null;

    public string $formOperationId = '';

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, string> */
    public array $formErrors = [];

    /** @var array<string, list<string>> */
    public array $formOffsets = [];

    /** @var array<string, mixed>|null */
    public ?array $pending = null;

    /** @var array<string, mixed>|null */
    public ?array $scopePrompt = null;

    /** @var array{type:string, message:string}|null */
    public ?array $toast = null;

    /** '', 'conflict' or 'unknown' */
    public string $outcomeState = '';

    public bool $movingEnabled = true;

    public ?bool $confirmMoveOverride = null;

    public bool $sideOpen = true;

    public string $quickMoveDate = '';

    public string $quickMoveTime = '';

    protected ?ResolvedCalendar $resolvedCache = null;

    protected bool $accessDenied = false;

    /** @return array<string, array<string, mixed>> */
    protected function queryString(): array
    {
        if (! $this->urlSync) {
            return [];
        }
        $p = $this->urlPrefix !== '' ? $this->urlPrefix.'_' : '';
        $cfg = fn (string $as): array => ['as' => $p.$as, 'history' => true, 'except' => ''];

        return [
            'view' => $cfg('view'),
            'system' => $cfg('system'),
            'year' => $cfg('year'),
            'month' => $cfg('month'),
            'day' => $cfg('day'),
            'search' => $cfg('q'),
            'disabledSources' => ['as' => $p.'off', 'history' => true, 'except' => []],
            'filters' => ['as' => $p.'f', 'history' => true, 'except' => []],
            'sideOpen' => ['as' => $p.'panel', 'history' => true, 'except' => true],
        ];
    }

    /**
     * @param  list<string>|null  $views  e.g. ['month','list']
     * @param  list<string>|null  $systems  e.g. ['jalali']
     * @param  list<string>|null  $sources  e.g. ['tasks','holidays']
     * @param  array<string, string>  $presetFilters  forced filters, e.g. ['status' => 'open']
     */
    public function mount(
        string $calendarKey,
        bool $urlSync = true,
        string $urlPrefix = '',
        string $layout = 'full',
        ?array $views = null,
        ?array $systems = null,
        ?array $sources = null,
        array $presetFilters = [],
        ?string $locale = null,
        ?string $defaultView = null,
        ?string $system = null,
        ?bool $movable = null,
        ?int $agendaLimit = null,
    ): void {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $calendarKey)) {
            abort(404);
        }
        if ($urlPrefix !== '' && ! preg_match('/^[a-z][a-z0-9]{0,19}$/', $urlPrefix)) {
            abort(404);
        }
        $this->calendarKey = $calendarKey;
        $this->urlSync = $urlSync;
        $this->urlPrefix = $urlPrefix;
        if ($movable === false) {
            $this->movingEnabled = false;
        }
        $this->embed = EmbedOptions::make($layout, $views, $systems, $sources, $presetFilters, $locale, $defaultView, $system, $movable, $agendaLimit)->toArray();
    }

    public function toggleMoving(): void
    {
        $this->guarded(function (ResolvedCalendar $r): void {
            if (! $r->definition->settings->feature('move') || ! (bool) config('calendar.features.move', true)) {
                $this->movingEnabled = false;

                return;
            }
            $this->movingEnabled = ! $this->movingEnabled;
        });
    }

    public function toggleConfirmMove(): void
    {
        $this->guarded(function (ResolvedCalendar $r): void {
            $current = $this->confirmMoveOverride ?? $r->definition->settings->feature('confirmMove');
            $this->confirmMoveOverride = ! $current;
        });
    }

    public function toggleSidePanel(): void
    {
        $this->guarded(function (ResolvedCalendar $r): void {
            if (! $r->definition->settings->feature('filters')) {
                return;
            }
            $this->sideOpen = ! $this->sideOpen;
        });
    }

    protected function embedOptions(): EmbedOptions
    {
        return EmbedOptions::fromArray($this->embed);
    }

    /** Resolve (definition + context) once per request. Throws CalendarAccessDenied for guests/unknown keys. */
    protected function resolved(): ResolvedCalendar
    {
        return $this->resolvedCache ??= $this->embedOptions()->apply(app(CalendarRuntime::class)->resolve($this->calendarKey));
    }

    /** Run an action that needs the resolved calendar; access problems become a safe denied state. */
    protected function guarded(callable $callback): mixed
    {
        try {
            return $callback($this->resolved());
        } catch (CalendarAccessDenied) {
            $this->accessDenied = true;
            $this->closeAllDialogs();

            return null;
        }
    }

    protected function closeAllDialogs(): void
    {
        $this->drawerDate = null;
        $this->detailsRef = null;
        $this->formOpen = false;
        $this->pending = null;
        $this->scopePrompt = null;
    }

    protected function notify(string $type, string $message): void
    {
        $this->toast = ['type' => $type, 'message' => $message];
    }

    public function dismissToast(): void
    {
        $this->toast = null;
        $this->outcomeState = '';
    }

    #[On('kw-calendar-refresh')]
    public function refreshNow(bool $syncSettings = false): void
    {
        // Intentionally empty by default: re-rendering re-reads authorised data from the providers.
        $this->outcomeState = '';
        if ($syncSettings) {
            $this->closeAllDialogs();
            $this->toast = null;
            $this->guarded(function (ResolvedCalendar $r): void {
                $this->movingEnabled = $r->definition->settings->feature('move') && (bool) config('calendar.features.move', true);
                $this->confirmMoveOverride = null;
            });
        }
    }

    public function render(): View
    {
        return view('calendar::livewire.calendar', $this->buildViewModel());
    }
}
