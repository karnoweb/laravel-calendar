@php $cols = count($columns); @endphp
<div class="kw-week" style="--kw-cols: {{ $cols }}" role="grid" aria-label="{{ $title }}">
    <div class="kw-week__head" role="row">
        <div class="kw-week__corner" aria-hidden="true"></div>
        @foreach ($columns as $col)
            @php $day = $col['day']; @endphp
            <div class="kw-week__dayhead @if ($day->today) is-today @endif @if ($day->weekend) is-weekend @endif" role="columnheader" wire:key="head-{{ $day->iso }}">
                <span class="kw-week__dow">{{ $labels->weekdayName($day->weekday, $locale, true) }}</span>
                <button type="button" class="kw-week__date" wire:click="openDay('{{ $day->iso }}')" @if ($day->today) aria-current="date" @endif>{{ $digits($day->day) }}</button>
            </div>
        @endforeach
    </div>

    @if (collect($columns)->contains(fn ($c) => $c['allDay'] !== []))
        <div class="kw-week__allday" role="row">
            <div class="kw-week__gutter kw-week__gutter--label">{{ $t('details.all_day') }}</div>
            @foreach ($columns as $col)
                <div class="kw-week__alldaycell" role="gridcell" data-kw-drop-day="{{ $col['day']->iso }}" wire:key="ad-{{ $col['day']->iso }}">
                    @foreach ($col['allDay'] as $card)
                        <div wire:key="ad-{{ $col['day']->iso }}-{{ $card['ref'] }}">
                            @include($views['event'], ['card' => $card, 'compact' => true])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <div class="kw-week__scroll" data-kw-scroll>
        <div class="kw-week__grid">
            <div class="kw-week__gutter" aria-hidden="true">
                @foreach ($hours as $h)
                    <div class="kw-week__hour">{{ $digits(sprintf('%02d:00', $h)) }}</div>
                @endforeach
            </div>
            @foreach ($columns as $col)
                @php $day = $col['day']; @endphp
                <div class="kw-week__col @if ($day->today) is-today @endif @if ($day->weekend) is-weekend @endif" role="gridcell" data-kw-timecol="{{ $day->iso }}" wire:key="col-{{ $day->iso }}" @if ($canCreate) data-kw-creatable="1" @endif>
                    @foreach ($hours as $h)
                        <div class="kw-week__slot"></div>
                    @endforeach
                    @if ($day->today)
                        @php $nowLocal = \Carbon\Carbon::now($tz ?? config('calendar.timezone', 'UTC')); @endphp
                        <div class="kw-week__now" style="top: {{ round(($nowLocal->hour * 60 + $nowLocal->minute) / 1440 * 100, 3) }}%" aria-hidden="true"></div>
                    @endif
                    @foreach ($col['timed'] as $card)
                        <div
                            class="kw-week__event"
                            style="top: {{ $card['top'] }}%; height: {{ $card['height'] }}%; inset-inline-start: {{ $card['left'] }}%; width: {{ $card['width'] }}%"
                            wire:key="te-{{ $day->iso }}-{{ $card['ref'] }}"
                        >
                            @include($views['event'], ['card' => $card, 'compact' => false])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>
@if ($read->events === [])
    @include($views['empty'])
@endif
