<div class="kw-calendar__toolbar">
    <div class="kw-calendar__nav">
        <button type="button" class="kw-btn" wire:click="today">{{ $t('toolbar.today') }}</button>
        <button type="button" class="kw-btn kw-btn--icon" wire:click="previous" aria-label="{{ $t('toolbar.previous') }}"><svg class="kw-chev" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <button type="button" class="kw-btn kw-btn--icon" wire:click="next" aria-label="{{ $t('toolbar.next') }}"><svg class="kw-chev kw-chev--next" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <h2 class="kw-calendar__title" aria-live="polite">{{ $title }}</h2>
    </div>

    <div class="kw-calendar__tools">
        @if (($features['search'] ?? false))
            <input type="search" class="kw-input kw-input--search" wire:model.live.debounce.400ms="search" placeholder="{{ $t('toolbar.search') }}" aria-label="{{ $t('toolbar.search') }}" maxlength="120">
        @endif

        @if (in_array($viewName, ['month', 'list'], true) && ! $compact)
            <label class="kw-select-wrap">
                <span class="kw-sr">{{ $t('toolbar.go_to_month') }}</span>
                <select class="kw-input" aria-label="{{ $t('toolbar.go_to_month') }}" wire:change="goTo('{{ $pickerYear }}', $event.target.value)">
                    @foreach ($monthNames as $num => $name)
                        <option value="{{ $num }}" @selected($num === $pickerMonth)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if (count($enabledSystems) > 1 && ($features['systemToggle'] ?? true))
        <div class="kw-segmented" role="group" aria-label="{{ $t('toolbar.switch_system') }}">
            @foreach ($enabledSystems as $sys)
                <button type="button" class="kw-segmented__btn @if ($systemName === $sys) is-active @endif" aria-pressed="{{ $systemName === $sys ? 'true' : 'false' }}" wire:click="setSystem('{{ $sys }}')">{{ $t('toolbar.system.'.$sys) }}</button>
            @endforeach
        </div>
        @endif

        @if (count($enabledViews) > 1 && ($features['viewSwitcher'] ?? true))
        <label class="kw-select-wrap">
            <span class="kw-sr">{{ $t('a11y.calendar') }}</span>
            <select class="kw-input kw-viewselect" aria-label="{{ $t('a11y.calendar') }}" wire:change="setView($event.target.value)">
                @foreach ($enabledViews as $v)
                    <option value="{{ $v }}" @selected($viewName === $v)>{{ $t('toolbar.views.'.$v) }}</option>
                @endforeach
            </select>
        </label>
        @endif
    </div>
</div>
