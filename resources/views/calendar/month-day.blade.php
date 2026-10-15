@php
    $day = $cell['day'];
    $visible = array_slice($cell['cards'], 0, $monthLimit);
    $hidden = $cell['count'] - count($visible);
@endphp
<div
    class="kw-day @if ($day->outsideMonth) is-outside @endif @if ($day->today) is-today @endif @if ($day->weekend) is-weekend @endif"
    role="gridcell"
    data-kw-drop-day="{{ $day->iso }}"
    aria-label="{{ $dayLabel($day->iso) }}"
    @if ($day->today) aria-current="date" @endif
    wire:key="day-{{ $day->iso }}"
>
    <div class="kw-day__head">
        <button type="button" class="kw-day__num" wire:click="openDay('{{ $day->iso }}')">{{ $digits($day->day) }}</button>
        @if ($canCreate)
            <button type="button" class="kw-day__add" wire:click="openCreate('{{ $day->iso }}')" aria-label="{{ $t('toolbar.new_event') }}">+</button>
        @endif
    </div>
    <div class="kw-day__events">
        @foreach ($visible as $card)
            <div wire:key="ev-{{ $day->iso }}-{{ $card['ref'] }}">
                @include($views['event'], ['card' => $card, 'compact' => false])
            </div>
        @endforeach
        @if ($hidden > 0)
            <button type="button" class="kw-day__more" data-hidden="{{ $hidden }}" wire:click="openDay('{{ $day->iso }}')">{{ $t('states.more', ['count' => $digits($hidden)]) }}</button>
        @endif
    </div>
</div>
