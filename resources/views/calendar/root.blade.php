@php
    $locale = $locale ?? app()->getLocale();
    $dir = $dir ?? 'ltr';
    $t = fn (string $key, array $replace = []) => (string) trans('calendar::calendar.'.$key, $replace, $locale);
@endphp
<div
    class="kw-calendar {{ $extraClass ?? '' }} @if ($compact ?? false) kw-calendar--compact @endif"
    dir="{{ $dir }}"
    data-kw-calendar
    data-theme="{{ $theme ?? 'auto' }}"
    data-view="{{ $viewName ?? '' }}"
    data-poll="{{ $poll ?? 0 }}"
    data-locale="{{ $locale }}"
    data-i18n="{{ json_encode(['picked' => $t('a11y.picked_up'), 'moved' => $t('a11y.moved_to', ['target' => ':target']), 'dropped' => $t('a11y.dropped'), 'cancelled' => $t('a11y.cancelled'), 'online' => $t('states.back_online')], JSON_UNESCAPED_UNICODE) }}"
    role="region"
    aria-label="{{ $t('a11y.calendar') }}"
>
    @if (($status ?? 'ok') === 'denied')
        @include('calendar::calendar.error', ['kind' => 'denied'])
    @elseif (($status ?? 'ok') === 'rate_limited')
        @include('calendar::calendar.error', ['kind' => 'rate_limited'])
    @elseif (($status ?? 'ok') === 'error')
        @include('calendar::calendar.error', ['kind' => 'error'])
    @else
        @include('calendar::calendar.toolbar')

        <div class="kw-layout">
        <aside class="kw-side" @if ($compact ?? false) hidden @endif>
            @if ($canCreate)
                <button type="button" class="kw-create" wire:click="openCreate"><span class="kw-create__plus" aria-hidden="true">+</span>{{ $t('toolbar.new_event') }}</button>
            @endif
            @include('calendar::calendar.filters')
        </aside>
        <div class="kw-main">
        @include('calendar::calendar.offline')

        @if (! empty($failedSources))
            <div class="kw-calendar__notice" role="status">{{ $t('states.source_failed', ['sources' => implode(', ', array_map(fn ($l) => \Karnoweb\Calendar\Support\CalendarTranslations::label($l, $locale), $failedSources))]) }}</div>
        @endif

        @if ($outcomeState === 'conflict')
            @include('calendar::calendar.conflict', ['kind' => 'conflict'])
        @elseif ($outcomeState === 'unknown')
            @include('calendar::calendar.conflict', ['kind' => 'unknown'])
        @endif

        <div class="kw-calendar__body" wire:loading.class="is-loading" wire:target="previous,next,today,setView,setSystem,goTo,toggleSource,setFilter,resetFilters,updatedSearch,loadMore">
            @if ($viewName === 'month')
                @include('calendar::calendar.month')
            @elseif ($viewName === 'week' || $viewName === 'day')
                @include('calendar::calendar.week')
            @else
                @include('calendar::calendar.list')
            @endif
            <div class="kw-calendar__loading" wire:loading.delay wire:target="previous,next,today,setView,setSystem,goTo,toggleSource,setFilter,resetFilters,loadMore" role="status">{{ $t('states.loading') }}</div>
        </div>

        @if ($viewName !== 'list' && ($read->truncated || $read->hasMore))
            @include('calendar::calendar.pagination', ['paginationAction' => 'loadMore'])
        @endif
        </div>
        </div>

        @if ($drawer !== null)
            @include('calendar::calendar.day-events-drawer')
        @endif
        @if ($details !== null)
            @include($views['eventDetails'], ['card' => $details])
        @endif
        @if ($formOpen && $formSchema !== null)
            @include($views['eventForm'])
        @endif
        @if ($scopePromptView !== null)
            @include('calendar::calendar.scope-prompt')
        @endif
        @if ($pendingView !== null)
            @include('calendar::calendar.confirm-action')
        @endif

        @include('calendar::calendar.drag-preview')

        @if ($toast)
            <div class="kw-calendar__toast kw-calendar__toast--{{ $toast['type'] }}" role="{{ $toast['type'] === 'error' ? 'alert' : 'status' }}" data-kw-toast>
                <span>{{ $toast['message'] }}</span>
                <button type="button" class="kw-calendar__toast-close" wire:click="dismissToast" aria-label="{{ $t('details.close') }}">×</button>
            </div>
        @endif
        <div class="kw-calendar__live" aria-live="polite" data-kw-live></div>
    @endif

    @assets
        <style>{!! \Karnoweb\Calendar\Support\CalendarAssets::css() !!}</style>
        <script>{!! \Karnoweb\Calendar\Support\CalendarAssets::js() !!}</script>
    @endassets
</div>
