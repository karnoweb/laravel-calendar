@php use Karnoweb\Calendar\Support\CalendarTranslations as Tr; @endphp
<div class="kw-modal kw-modal--drawer modal modal-open" data-kw-modal data-kw-part="day-modal" wire:keydown.escape="closeDay">
    <div class="kw-modal__backdrop modal-backdrop" data-kw-part="modal-backdrop" data-kw-action="close-day" wire:click="closeDay"></div>
    <aside class="kw-drawer modal-box" data-kw-part="day-drawer" role="dialog" aria-modal="true" aria-labelledby="kw-day-title" tabindex="-1">
        <header class="kw-drawer__head" data-kw-part="drawer-header">
            <h3 id="kw-day-title" data-kw-part="drawer-title">{{ $t('states.day_events', ['date' => $drawer['title']]) }}</h3>
            <button type="button" class="kw-btn kw-btn--icon btn btn-sm btn-ghost btn-square" data-kw-part="drawer-close" data-kw-action="close-day" wire:click="closeDay" aria-label="{{ $t('details.close') }}" data-kw-autofocus>×</button>
        </header>
        <div class="kw-drawer__body" data-kw-part="drawer-body">
            @if ($drawer['cards'] === [])
                @include($views['empty'])
            @else
                <ul class="kw-drawer__list" data-kw-part="day-drawer-list">
                    @foreach ($drawer['cards'] as $card)
                        <li data-kw-part="day-drawer-item" wire:key="dr-{{ $card['ref'] }}">
                            @include($views['event'], ['card' => $card, 'compact' => false])
                            <small class="kw-drawer__source">{{ Tr::label($card['source']->label, $locale) }}</small>
                        </li>
                    @endforeach
                </ul>
                @if ($drawer['hasMore'])
                    @include('calendar::calendar.pagination', ['paginationAction' => 'loadMoreDay', 'read' => new \Karnoweb\Calendar\Data\ReadResult([], true, false, [], 'drawer')])
                @endif
            @endif
        </div>
        @if ($canCreate)
            <footer class="kw-drawer__foot modal-action" data-kw-part="drawer-footer">
                <button type="button" class="kw-btn kw-btn--primary btn btn-sm btn-primary" data-kw-part="day-drawer-create-btn" data-kw-action="create-day-event" wire:click="openCreate('{{ $drawer['iso'] }}')">{{ $t('toolbar.new_event') }}</button>
            </footer>
        @endif
    </aside>
</div>
