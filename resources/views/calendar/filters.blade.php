@php
    use Karnoweb\Calendar\Support\CalendarTranslations as Tr;
    $canConfigureMove = ($features['move'] ?? false) && (bool) config('calendar.features.move', true);
    $hasDisplayTools = (($canCreate ?? false) || (($features['search'] ?? false) && ! ($compact ?? false)) || (count($enabledViews ?? []) > 1 && ($features['viewSwitcher'] ?? true)) || (count($enabledSystems ?? []) > 1 && ($features['systemToggle'] ?? true)));
@endphp
@if ($hasDisplayTools || count($sources) > 1 || count($filtersUi) > 0 || $canConfigureMove)
    <div class="kw-calendar__filters" data-kw-part="sidebar-filters" role="group" aria-label="{{ $t('toolbar.filters') }}">
        @if ($hasDisplayTools)
            <div class="kw-side__group kw-side__mobile-tools" data-kw-part="sidebar-mobile-tools">
                <h3 class="kw-side__title" data-kw-part="sidebar-section-title">{{ $t('toolbar.display_tools') }}</h3>

                @if ($canCreate ?? false)
                    <button type="button" class="kw-create btn btn-sm btn-primary btn-block" data-kw-side-autoclose data-kw-part="sidebar-create-btn" data-kw-action="create-event" wire:click="openCreate">
                        <span class="kw-create__plus" aria-hidden="true">+</span>{{ $t('toolbar.new_event') }}
                    </button>
                @endif

                @if (($features['search'] ?? false) && ! ($compact ?? false))
                    <div class="kw-search-wrap kw-search-wrap--block" data-kw-part="sidebar-search-wrap">
                        <svg class="kw-search-icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="search" class="kw-input kw-input--search input input-sm" data-kw-part="sidebar-search-input" data-kw-action="search" wire:model.live.debounce.400ms="search" placeholder="{{ $t('toolbar.search') }}" aria-label="{{ $t('toolbar.search') }}" maxlength="120">
                    </div>
                @endif

                @if (count($enabledViews ?? []) > 1 && ($features['viewSwitcher'] ?? true))
                    <div class="kw-side__sub" data-kw-part="sidebar-view-switcher-group">
                        <span class="kw-side__sublabel">{{ $t('toolbar.switch_view') }}</span>
                        <div class="kw-segmented kw-segmented--block tabs tabs-box tabs-sm" data-kw-part="sidebar-view-switcher" role="group" aria-label="{{ $t('toolbar.switch_view') }}">
                            @foreach ($enabledViews as $v)
                                <button type="button" class="kw-segmented__btn tab @if (($viewName ?? '') === $v) is-active tab-active @endif" data-kw-part="sidebar-view-btn" data-kw-action="set-view" data-kw-view="{{ $v }}" aria-pressed="{{ ($viewName ?? '') === $v ? 'true' : 'false' }}" data-kw-side-autoclose wire:click="setView('{{ $v }}')">{{ $t('toolbar.views.'.$v) }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (count($enabledSystems ?? []) > 1 && ($features['systemToggle'] ?? true))
                    <div class="kw-side__sub" data-kw-part="sidebar-system-switcher-group">
                        <span class="kw-side__sublabel">{{ $t('toolbar.switch_system') }}</span>
                        <div class="kw-segmented kw-segmented--block tabs tabs-box tabs-sm" data-kw-part="sidebar-system-switcher" role="group" aria-label="{{ $t('toolbar.switch_system') }}">
                            @foreach ($enabledSystems as $sys)
                                <button type="button" class="kw-segmented__btn tab @if (($systemName ?? '') === $sys) is-active tab-active @endif" data-kw-part="sidebar-system-btn" data-kw-action="set-system" data-kw-system="{{ $sys }}" aria-pressed="{{ ($systemName ?? '') === $sys ? 'true' : 'false' }}" wire:click="setSystem('{{ $sys }}')">{{ $t('toolbar.system.'.$sys) }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (in_array($viewName ?? '', ['month', 'list'], true) && ! ($compact ?? false) && ! empty($monthNames))
                    <div class="kw-side__sub" data-kw-part="sidebar-month-picker-group">
                        <span class="kw-side__sublabel">{{ $t('toolbar.go_to_month') }}</span>
                        <select class="kw-input select select-sm" data-kw-part="sidebar-month-picker" data-kw-action="go-to-month" aria-label="{{ $t('toolbar.go_to_month') }}" wire:change="goTo('{{ $pickerYear }}', $event.target.value)">
                            @foreach ($monthNames as $num => $name)
                                <option value="{{ $num }}" @selected($num === ($pickerMonth ?? null))>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        @endif

        @if (count($sources) > 1)
            <div class="kw-side__group" data-kw-part="sources-group">
                <h3 class="kw-side__title" data-kw-part="sidebar-section-title">{{ $t('toolbar.sources') }}</h3>
                @foreach ($sources as $source)
                    <button
                        type="button"
                        class="kw-chip btn btn-sm btn-ghost justify-start {{ $source['colorClass'] }} @if ($source['enabled']) is-on @endif @if ($source['failed']) is-failed @endif"
                        style="{{ $source['colorStyle'] }}"
                        data-kw-part="source-chip"
                        data-kw-action="toggle-source"
                        data-kw-source="{{ $source['key'] }}"
                        aria-pressed="{{ $source['enabled'] ? 'true' : 'false' }}"
                        wire:click="toggleSource('{{ $source['key'] }}')"
                        wire:key="src-{{ $source['key'] }}"
                    >
                        <span class="kw-chip__dot" aria-hidden="true"></span><span class="kw-chip__label">{{ Tr::label($source['label'], $locale) }}</span><span class="kw-chip__check" aria-hidden="true"></span>
                    </button>
                @endforeach
            </div>
        @endif

        @if (count($filtersUi) > 0)
            <div class="kw-side__group" data-kw-part="filters-group">
                <h3 class="kw-side__title" data-kw-part="sidebar-section-title">{{ $t('toolbar.filters') }}</h3>
                @foreach ($filtersUi as $filter)
                    <label class="kw-filter" data-kw-part="filter-item" data-kw-filter="{{ $filter['key'] }}" wire:key="flt-{{ $filter['key'] }}">
                        <span class="kw-sr">{{ Tr::label($filter['label'], $locale) }}</span>
                        <select class="kw-input select select-sm" data-kw-part="filter-select" data-kw-action="set-filter" aria-label="{{ Tr::label($filter['label'], $locale) }}" wire:change="setFilter('{{ $filter['key'] }}', $event.target.value)">
                            <option value="">{{ Tr::label($filter['label'], $locale) }}: {{ $t('toolbar.all') }}</option>
                            @foreach ($filter['options'] as $value => $label)
                                <option value="{{ $value }}" @selected((string) $filter['selected'] === (string) $value)>{{ Tr::label((string) $label, $locale) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach

                @if ($hasActiveFilters)
                    <button type="button" class="kw-btn kw-btn--ghost btn btn-sm btn-ghost" data-kw-part="reset-filters-btn" data-kw-action="reset-filters" wire:click="resetFilters">{{ $t('toolbar.reset_filters') }}</button>
                @endif
            </div>
        @endif

        @if ($canConfigureMove)
            <div class="kw-side__group kw-side__group--settings" data-kw-part="settings-group">
                <h3 class="kw-side__title" data-kw-part="sidebar-section-title">{{ $t('settings.title') }}</h3>
                <button
                    type="button"
                    class="kw-toggle-row @if ($movingEnabled ?? true) is-on @endif"
                    data-kw-part="toggle-moving-btn"
                    data-kw-action="toggle-moving"
                    role="switch"
                    aria-checked="{{ ($movingEnabled ?? true) ? 'true' : 'false' }}"
                    wire:click="toggleMoving"
                >
                    <span class="kw-toggle-row__label">{{ $t('settings.allow_move') }}</span>
                    <span class="kw-toggle-row__switch" aria-hidden="true"></span>
                </button>
                <button
                    type="button"
                    class="kw-toggle-row @if ($confirmMoveEnabled ?? false) is-on @endif"
                    data-kw-part="toggle-confirm-move-btn"
                    data-kw-action="toggle-confirm-move"
                    role="switch"
                    aria-checked="{{ ($confirmMoveEnabled ?? false) ? 'true' : 'false' }}"
                    wire:click="toggleConfirmMove"
                >
                    <span class="kw-toggle-row__label">{{ $t('settings.confirm_move') }}</span>
                    <span class="kw-toggle-row__switch" aria-hidden="true"></span>
                </button>
            </div>
        @endif
    </div>
@endif
