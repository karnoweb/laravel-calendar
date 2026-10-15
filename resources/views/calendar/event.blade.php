{{-- Receives $card (see docs/STYLING-AND-VIEWS.md). Text is always escaped. --}}
@php
    $e = $card['event'];
    $compact = $compact ?? false;
    $startMinute = 0;
    if (! $e->allDay && in_array($card['position'], ['single', 'start'], true)) {
        $local = $e->startAt($card['tz']);
        $startMinute = (int) $local->format('G') * 60 + (int) $local->format('i');
    }
@endphp
<div
    class="kw-event {{ $card['colorClass'] }} kw-event--{{ $card['position'] }} @if ($e->allDay) kw-event--allday @endif @if ($card['readOnly']) is-readonly @endif"
    style="{{ $card['colorStyle'] }}"
    role="button"
    tabindex="0"
    data-kw-event
    data-ref="{{ $card['ref'] }}"
    data-version="{{ $card['version'] }}"
    data-day="{{ $card['dayIso'] }}"
    data-movable="{{ $card['movable'] ? '1' : '0' }}"
    data-resizable="{{ $card['resizable'] ? '1' : '0' }}"
    data-allday="{{ $e->allDay ? '1' : '0' }}"
    data-minute="{{ $startMinute }}"
    aria-label="{{ $e->title }}@if ($card['timeRange']), {{ $card['timeRange'] }}@endif"
    @if ($card['movable']) aria-describedby="kw-move-hint" @endif
    wire:click="openEvent('{{ $card['ref'] }}')"
>
    <span class="kw-event__dot" aria-hidden="true"></span>
    @if ($card['timeLabel'] && ! $compact)<span class="kw-event__time">{{ $card['timeLabel'] }}</span>@endif
    <span class="kw-event__title">{{ $e->title }}</span>
    @if (! $compact && isset($views['eventPopover']))@include($views['eventPopover'], ['card' => $card])@endif
    @if ($card['resizable'])<span class="kw-event__resize" data-kw-resize aria-hidden="true"></span>@endif
</div>
