@php
    $day = $cell['day'];
    $visible = array_slice($cell['cards'], 0, $monthLimit);
    $hidden = max(0, $cell['count'] - count($visible) - ($cell['spanCount'] ?? 0));
    $isHoliday = collect($cell['cards'])->contains(fn ($c) => $c['event']->category === 'holiday');
    $isSelected = ($drawerDate ?? null) === $day->iso;
@endphp
<div
    class="kw-day @if ($isHoliday) is-holiday @endif @if ($day->outsideMonth) is-outside @endif @if ($day->today) is-today @endif @if ($isSelected) is-selected @endif @if ($day->weekend) is-weekend @endif"
    role="gridcell"
    data-kw-drop-day="{{ $day->iso }}"
    aria-label="{{ $dayLabel($day->iso) }}"
    @if ($day->today) aria-current="date" @endif
    wire:key="day-{{ $day->iso }}"
>
    <div class="kw-day__head">
        <button type="button" class="kw-day__num" wire:click="openDay('{{ $day->iso }}')">{{ $digits($day->day) }}</button>
        <div class="kw-day__head-end">
            @if ($canCreate)
                <button type="button" class="kw-day__add" wire:click="openCreate('{{ $day->iso }}')" aria-label="{{ $t('toolbar.new_event') }}">+</button>
            @endif
            <span class="kw-day__alt" aria-hidden="true">{{ $altDay($day->iso) }}</span>
        </div>
    </div>
    @if ($isHoliday)<span class="kw-day__holiday">{{ $t('states.holiday') }}</span>@endif
    @if (! empty($cell['markers']))
        <div class="kw-day__markers" aria-hidden="true">
            @foreach (array_slice($cell['markers'], 0, 4, true) as $markerClass => $markerStyle)
                <span class="kw-day__marker {{ is_int($markerClass) ? $markerStyle : $markerClass }}" @if (! is_int($markerClass) && $markerStyle) style="{{ $markerStyle }}" @endif></span>
            @endforeach
        </div>
    @endif
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
