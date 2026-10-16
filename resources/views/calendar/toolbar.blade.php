<div class="kw-calendar__toolbar navbar" data-kw-part="toolbar">
    <div class="kw-calendar__toolbar-start navbar-start" data-kw-part="toolbar-start">
        @if (($showSidePanel ?? false) || ($hasMobileDrawer ?? false))
            <button
                type="button"
                class="kw-btn kw-btn--icon kw-btn--menu btn btn-sm btn-square btn-ghost @if (($showSidePanel ?? false) && ($sideOpen ?? true)) is-active btn-active @endif @if (! ($showSidePanel ?? false)) kw-mobile-only @endif"
                data-kw-side-toggle
                data-kw-part="sidebar-toggle"
                data-kw-action="toggle-sidebar"
                wire:click="toggleSidePanel"
                aria-expanded="{{ (($showSidePanel ?? false) && ($sideOpen ?? true)) ? 'true' : 'false' }}"
                aria-controls="kw-side-panel"
                aria-label="{{ (($showSidePanel ?? false) && ($sideOpen ?? true)) ? $t('toolbar.hide_panel') : $t('toolbar.show_panel') }}"
            >
                <svg class="kw-menu-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        @endif
    </div>

    <div class="kw-calendar__nav" data-kw-part="toolbar-nav">
        <button type="button" class="kw-btn kw-btn--today btn btn-sm btn-outline" data-kw-part="today-btn" data-kw-action="today" wire:click="today">{{ $t('toolbar.today') }}</button>
        <div class="kw-calendar__arrows join" data-kw-part="nav-arrows">
            <button type="button" class="kw-btn kw-btn--icon btn btn-sm btn-ghost btn-square join-item" data-kw-part="prev-btn" data-kw-action="previous" wire:click="previous" aria-label="{{ $t('toolbar.previous') }}"><svg class="kw-chev" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            <button type="button" class="kw-btn kw-btn--icon btn btn-sm btn-ghost btn-square join-item" data-kw-part="next-btn" data-kw-action="next" wire:click="next" aria-label="{{ $t('toolbar.next') }}"><svg class="kw-chev kw-chev--next" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        </div>
        <h2 class="kw-calendar__title" data-kw-part="toolbar-title" aria-live="polite">{{ $title }}</h2>

        @if (in_array($viewName, ['month', 'list'], true) && ! $compact)
            <label class="kw-select-wrap kw-desktop-only" data-kw-part="month-picker-wrap">
                <span class="kw-sr">{{ $t('toolbar.go_to_month') }}</span>
                <select class="kw-input kw-monthselect select select-sm" data-kw-part="month-picker" data-kw-action="go-to-month" aria-label="{{ $t('toolbar.go_to_month') }}" wire:change="goTo('{{ $pickerYear }}', $event.target.value)">
                    @foreach ($monthNames as $num => $name)
                        <option value="{{ $num }}" @selected($num === $pickerMonth)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    @if (($canCreate ?? false) && ! ($compact ?? false))
        <button type="button" class="kw-create kw-create--mobile-bar btn btn-sm btn-primary" data-kw-part="mobile-create-btn" data-kw-action="create-event" wire:click="openCreate" aria-label="{{ $t('toolbar.new_event') }}">
            <span class="kw-create__plus" aria-hidden="true">+</span>
            <span class="kw-create__text">{{ $t('toolbar.new_event') }}</span>
        </button>
    @endif

    <div class="kw-calendar__tools navbar-end" data-kw-part="toolbar-tools">
        @if (($features['search'] ?? false) && ! $compact)
            <div class="kw-search-wrap" data-kw-part="search-wrap">
                <svg class="kw-search-icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" class="kw-input kw-input--search input input-sm" data-kw-part="search-input" data-kw-action="search" wire:model.live.debounce.400ms="search" placeholder="{{ $t('toolbar.search') }}" aria-label="{{ $t('toolbar.search') }}" maxlength="120">
            </div>
        @endif

        @if (count($enabledSystems) > 1 && ($features['systemToggle'] ?? true))
        <div class="kw-segmented tabs tabs-box tabs-sm" data-kw-part="system-switcher" role="group" aria-label="{{ $t('toolbar.switch_system') }}">
            @foreach ($enabledSystems as $sys)
                <button type="button" class="kw-segmented__btn tab @if ($systemName === $sys) is-active tab-active @endif" data-kw-part="system-btn" data-kw-action="set-system" data-kw-system="{{ $sys }}" aria-pressed="{{ $systemName === $sys ? 'true' : 'false' }}" wire:click="setSystem('{{ $sys }}')">{{ $t('toolbar.system.'.$sys) }}</button>
            @endforeach
        </div>
        @endif

        @if (count($enabledViews) > 1 && ($features['viewSwitcher'] ?? true))
        <div class="kw-segmented kw-segmented--views tabs tabs-box tabs-sm" data-kw-part="view-switcher" role="group" aria-label="{{ $t('toolbar.switch_view') }}">
            @foreach ($enabledViews as $v)
                <button type="button" class="kw-segmented__btn tab @if ($viewName === $v) is-active tab-active @endif" data-kw-part="view-btn" data-kw-action="set-view" data-kw-view="{{ $v }}" aria-pressed="{{ $viewName === $v ? 'true' : 'false' }}" wire:click="setView('{{ $v }}')">{{ $t('toolbar.views.'.$v) }}</button>
            @endforeach
        </div>
        @endif

        @if ($canCreate ?? false)
            <button type="button" class="kw-create kw-create--toolbar btn btn-sm btn-primary" data-kw-part="create-btn" data-kw-action="create-event" wire:click="openCreate">
                <span class="kw-create__plus" aria-hidden="true">+</span>{{ $t('toolbar.new_event') }}
            </button>
        @endif
    </div>
</div>
