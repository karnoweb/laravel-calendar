{{-- Host override of the event card. Receives the same $card + $compact variables as the package default. --}}
@php $e = $card['event']; @endphp
<div
    class="kw-event {{ $card['colorClass'] }} kw-event--{{ $card['position'] }} @if ($e->allDay) kw-event--allday @endif @if ($card['readOnly']) is-readonly @endif"
    style="{{ $card['colorStyle'] }}"
    role="button"
    tabindex="0"
    data-kw-event
    data-custom-card="1"
    data-ref="{{ $card['ref'] }}"
    data-version="{{ $card['version'] }}"
    data-day="{{ $card['dayIso'] }}"
    data-movable="{{ $card['movable'] ? '1' : '0' }}"
    data-resizable="{{ $card['resizable'] ? '1' : '0' }}"
    data-allday="{{ $e->allDay ? '1' : '0' }}"
    data-minute="0"
    wire:click="openEvent('{{ $card['ref'] }}')"
>
    <span class="kw-event__title">★ {{ $e->title }}</span>
    @if ($card['timeLabel'])<span class="kw-event__time">{{ $card['timeLabel'] }}</span>@endif
    @if ($card['resizable'])<span class="kw-event__resize" data-kw-resize aria-hidden="true"></span>@endif
</div>
