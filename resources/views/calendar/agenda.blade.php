@php
    $items = $agendaItems ?? [];
@endphp
<div class="kw-agenda" data-kw-part="agenda-view" role="region" aria-label="{{ $t('views.list') }}">
    @if($items === [])
        @include($views['empty'])
    @else
        @foreach($items as $item)
            @php($ev = $item['card'])
            @php($eventObj = $ev['event'])
            <div
                class="kw-agenda__card card card-border {{ $ev['colorClass'] }} {{ ($item['isToday'] ?? false) ? 'is-today' : '' }}"
                data-kw-part="agenda-card"
                data-kw-action="open-event"
                @if($ev['colorStyle']) style="{{ $ev['colorStyle'] }}" @endif
                wire:click="openEvent(@js($ev['ref']))"
                role="button"
                tabindex="0"
                @keydown.enter.prevent="$wire.openEvent(@js($ev['ref']))"
            >
                <div class="kw-agenda__date {{ ($item['isWeekend'] ?? false) ? 'is-weekend' : '' }}" data-kw-part="agenda-date">
                    <span class="kw-agenda__dow">{{ $item['weekday'] }}</span>
                    <strong class="kw-agenda__day">{{ $item['dayNum'] }}</strong>
                </div>

                <div class="kw-agenda__icon" data-kw-part="agenda-icon" aria-hidden="true">
                    @if(($ev['source']->key ?? '') === 'holidays')
                        <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 3v14M4 4h10l-2 3.5L14 11H4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @elseif(($ev['source']->key ?? '') === 'google')
                        <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="14" height="13" rx="2.5"/><path d="M3 8h14M7 2.5v3M13 2.5v3" stroke-linecap="round"/></svg>
                    @elseif($eventObj->isRecurring)
                        <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 4l3 3-3 3M3 10V8a3 3 0 013-3h11M6 16l-3-3 3-3M17 10v2a3 3 0 01-3 3H3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @else
                        <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11l2 2 4-4M4 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @endif
                </div>

                <div class="kw-agenda__body" data-kw-part="agenda-body">
                    <div class="kw-agenda__title-row">
                        <span class="kw-agenda__title" data-kw-part="agenda-title">{{ $eventObj->title }}</span>
                        @if(!($ev['movable'] ?? false) && !($ev['readOnly'] ?? false))
                            <span class="kw-agenda__lock" data-kw-part="agenda-lock" title="{{ $t('details.movable_off') }}">
                                <svg viewBox="0 0 16 16" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="7" width="9" height="6.5" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke-linecap="round"/></svg>
                            </span>
                        @endif
                    </div>
                    <div class="kw-agenda__meta" data-kw-part="agenda-meta">
                        @if(!empty($item['sourceLabel']))
                            <span class="kw-agenda__badge badge badge-xs badge-soft" data-kw-part="agenda-badge">{{ $item['sourceLabel'] }}</span>
                        @endif
                        <span class="kw-agenda__time" data-kw-part="agenda-time">{{ $item['timeText'] }}</span>
                    </div>
                </div>

                <span class="kw-agenda__accent" aria-hidden="true"></span>
            </div>
        @endforeach
    @endif
</div>
