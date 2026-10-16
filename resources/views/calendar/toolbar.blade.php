<div class="kw-calendar__toolbar">
    <div class="kw-calendar__toolbar-start">
        @if (($showSidePanel ?? false) || ($hasMobileDrawer ?? false))
            <button
                type="button"
                class="kw-btn kw-btn--icon kw-btn--menu @if (($showSidePanel ?? false) && ($sideOpen ?? true)) is-active @endif @if (! ($showSidePanel ?? false)) kw-mobile-only @endif"
                data-kw-side-toggle
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

    <div class="kw-calendar__nav">
        <button type="button" class="kw-btn kw-btn--today" wire:click="today">{{ $t('toolbar.today') }}</button>
        <div class="kw-calendar__arrows">
            <button type="button" class="kw-btn kw-btn--icon" wire:click="previous" aria-label="{{ $t('toolbar.previous') }}"><svg class="kw-chev" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            <button type="button" class="kw-btn kw-btn--icon" wire:click="next" aria-label="{{ $t('toolbar.next') }}"><svg class="kw-chev kw-chev--next" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        </div>
        <h2 class="kw-calendar__title" aria-live="polite">{{ $title }}</h2>

        @if (in_array($viewName, ['month', 'list'], true) && ! $compact)
            <label class="kw-select-wrap kw-desktop-only">
                <span class="kw-sr">{{ $t('toolbar.go_to_month') }}</span>
                <select class="kw-input kw-monthselect" aria-label="{{ $t('toolbar.go_to_month') }}" wire:change="goTo('{{ $pickerYear }}', $event.target.value)">
                    @foreach ($monthNames as $num => $name)
                        <option value="{{ $num }}" @selected($num === $pickerMonth)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    @if (($canCreate ?? false) && ! ($compact ?? false))
        <button type="button" class="kw-create kw-create--mobile-bar" wire:click="openCreate" aria-label="{{ $t('toolbar.new_event') }}">
            <span class="kw-create__plus" aria-hidden="true">+</span>
            <span class="kw-create__text">{{ $t('toolbar.new_event') }}</span>
        </button>
    @endif

    <div class="kw-calendar__tools">
        @if (($features['search'] ?? false) && ! $compact)
            <div class="kw-search-wrap">
                <svg class="kw-search-icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" class="kw-input kw-input--search" wire:model.live.debounce.400ms="search" placeholder="{{ $t('toolbar.search') }}" aria-label="{{ $t('toolbar.search') }}" maxlength="120">
            </div>
        @endif

        @if (count($enabledSystems) > 1 && ($features['systemToggle'] ?? true))
        <div class="kw-segmented" role="group" aria-label="{{ $t('toolbar.switch_system') }}">
            @foreach ($enabledSystems as $sys)
                <button type="button" class="kw-segmented__btn @if ($systemName === $sys) is-active @endif" aria-pressed="{{ $systemName === $sys ? 'true' : 'false' }}" wire:click="setSystem('{{ $sys }}')">{{ $t('toolbar.system.'.$sys) }}</button>
            @endforeach
        </div>
        @endif

        @if (count($enabledViews) > 1 && ($features['viewSwitcher'] ?? true))
        <div class="kw-segmented kw-segmented--views" role="group" aria-label="{{ $t('a11y.calendar') }}">
            @foreach ($enabledViews as $v)
                <button type="button" class="kw-segmented__btn @if ($viewName === $v) is-active @endif" aria-pressed="{{ $viewName === $v ? 'true' : 'false' }}" wire:click="setView('{{ $v }}')">{{ $t('toolbar.views.'.$v) }}</button>
            @endforeach
        </div>
        @endif

        @if ($canCreate ?? false)
            <button type="button" class="kw-create kw-create--toolbar" wire:click="openCreate">
                <span class="kw-create__plus" aria-hidden="true">+</span>{{ $t('toolbar.new_event') }}
            </button>
        @endif
    </div>
</div>
