{{-- Receives $card (see docs/STYLING-AND-VIEWS.md). Text is always escaped. --}}
@php
    $e = $card['event'];
    $compact = $compact ?? false;
    $spanBar = $spanBar ?? false;
    $continuesBefore = $continuesBefore ?? false;
    $continuesAfter = $continuesAfter ?? false;
    $startMinute = 0;
    $endMinute = 60;
    if (! $e->allDay) {
        if (in_array($card['position'], ['single', 'start'], true)) {
            $local = $e->startAt($card['tz']);
            $startMinute = (int) $local->format('G') * 60 + (int) $local->format('i');
        }
        $endLocal = $e->endAt($card['tz']);
        $endMinute = (int) $endLocal->format('G') * 60 + (int) $endLocal->format('i');
        if ($endMinute === 0) {
            $endMinute = 1440;
        }
    }
@endphp
<div
    class="kw-event {{ $card['colorClass'] }} kw-event--{{ $card['position'] }} @if ($e->allDay) kw-event--allday @endif @if ($spanBar) kw-event--span @endif @if ($card['readOnly']) is-readonly @endif @if (! $card['movable']) is-locked @endif"
    style="{{ $card['colorStyle'] }}"
    role="button"
    tabindex="0"
    data-kw-event
    data-kw-part="event-card"
    data-kw-action="open-event"
    data-ref="{{ $card['ref'] }}"
    data-version="{{ $card['version'] }}"
    data-day="{{ $card['dayIso'] }}"
    data-movable="{{ $card['movable'] ? '1' : '0' }}"
    data-resizable="{{ $card['resizable'] ? '1' : '0' }}"
    data-allday="{{ $e->allDay ? '1' : '0' }}"
    data-minute="{{ $startMinute }}"
    data-end-minute="{{ $endMinute }}"
    aria-label="{{ $e->title }}@if ($card['timeRange']), {{ $card['timeRange'] }}@endif"
    @if ($card['movable']) aria-describedby="kw-move-hint" @endif
    wire:click="openEvent('{{ $card['ref'] }}')"
>
    <span class="kw-event__dot" aria-hidden="true"></span>
    @if ($continuesBefore)
        <span class="kw-event__cont kw-event__cont--before" aria-hidden="true">‹</span>
    @endif
    <span class="kw-event__content" data-kw-part="event-content">
        <span class="kw-event__top" data-kw-part="event-top">
            <span class="kw-event__title" data-kw-part="event-title" dir="auto">{{ $e->title }}</span>
            @if (! $card['movable'])
                <span class="kw-event__lock" data-kw-part="event-lock" title="{{ $t('details.movable_off') }}" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
            @endif
        </span>
        @if ($card['timeLabel'] && ! $compact)
            <span class="kw-event__time" data-kw-part="event-time" dir="auto">{{ (($viewName ?? '') === 'month' || ($card['lanes'] ?? 1) > 1) ? $card['timeLabel'] : ($card['timeRange'] ?: $card['timeLabel']) }}</span>
        @endif
    </span>
    @if ($continuesAfter)
        <span class="kw-event__cont kw-event__cont--after" aria-hidden="true">›</span>
    @endif
    @if (! $compact && isset($views['eventPopover']))@include($views['eventPopover'], ['card' => $card])@endif
    @if ($card['resizable'] && in_array($card['position'], ['single', 'end'], true))<span class="kw-event__resize" data-kw-resize data-kw-part="event-resize" data-kw-action="resize-event" aria-hidden="true"></span>@endif
</div>
