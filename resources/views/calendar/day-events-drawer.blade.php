@php use Karnoweb\Calendar\Support\CalendarTranslations as Tr; @endphp
<div class="kw-modal kw-modal--drawer" data-kw-modal wire:keydown.escape="closeDay">
    <div class="kw-modal__backdrop" wire:click="closeDay"></div>
    <aside class="kw-drawer" role="dialog" aria-modal="true" aria-labelledby="kw-day-title" tabindex="-1">
        <header class="kw-drawer__head">
            <h3 id="kw-day-title">{{ $t('states.day_events', ['date' => $drawer['title']]) }}</h3>
            <button type="button" class="kw-btn kw-btn--icon" wire:click="closeDay" aria-label="{{ $t('details.close') }}" data-kw-autofocus>×</button>
        </header>
        <div class="kw-drawer__body">
            @if ($drawer['cards'] === [])
                @include($views['empty'])
            @else
                <ul class="kw-drawer__list">
                    @foreach ($drawer['cards'] as $card)
                        <li wire:key="dr-{{ $card['ref'] }}">
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
            <footer class="kw-drawer__foot">
                <button type="button" class="kw-btn kw-btn--primary" wire:click="openCreate('{{ $drawer['iso'] }}')">{{ $t('toolbar.new_event') }}</button>
            </footer>
        @endif
    </aside>
</div>
