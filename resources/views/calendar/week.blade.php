@php $cols = count($columns); @endphp
<div class="kw-week @if ($cols > 1) kw-week--multi @endif" data-kw-part="week-view" style="--kw-cols: {{ $cols }}" role="grid" aria-label="{{ $title }}">
    <div class="kw-week__scroll" data-kw-scroll data-kw-part="week-scroll">
        <div class="kw-week__sticky-top" data-kw-part="week-sticky-header">
            <div class="kw-week__head" data-kw-part="week-header" role="row">
                <div class="kw-week__corner" aria-hidden="true"></div>
                @foreach ($columns as $col)
                    @php $day = $col['day']; @endphp
                    <div class="kw-week__dayhead @if ($day->today) is-today @endif @if ($day->weekend) is-weekend @endif" data-kw-part="week-day-header" role="columnheader" wire:key="head-{{ $day->iso }}">
                        <span class="kw-week__dow">{{ $labels->weekdayName($day->weekday, $locale, true) }}</span>
                        <button type="button" class="kw-week__date btn btn-sm btn-ghost" data-kw-part="week-date-btn" data-kw-action="open-day" tabindex="{{ $day->today || $loop->first ? '0' : '-1' }}" wire:click="openDay('{{ $day->iso }}')" @if ($day->today) aria-current="date" @endif>{{ $digits($day->day) }}</button>
                    </div>
                @endforeach
            </div>

            @if (collect($columns)->contains(fn ($c) => $c['allDay'] !== []))
                <div class="kw-week__allday" data-kw-part="week-allday-row" role="row">
                    <div class="kw-week__gutter kw-week__gutter--label" data-kw-part="week-allday-gutter">{{ $t('details.all_day') }}</div>
                    @foreach ($columns as $col)
                        <div class="kw-week__alldaycell" data-kw-part="week-allday-cell" role="gridcell" data-kw-drop-day="{{ $col['day']->iso }}" wire:key="ad-{{ $col['day']->iso }}">
                            @foreach ($col['allDay'] as $card)
                                <div wire:key="ad-{{ $col['day']->iso }}-{{ $card['ref'] }}">
                                    @include($views['event'], ['card' => $card, 'compact' => true])
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="kw-week__grid" data-kw-part="week-grid" role="row">
            <div class="kw-week__gutter" data-kw-part="week-time-gutter" aria-hidden="true">
                @foreach ($hours as $h)
                    <div class="kw-week__hour" data-kw-part="week-hour-label">{{ $digits(sprintf('%02d:00', $h)) }}</div>
                @endforeach
            </div>
            @foreach ($columns as $col)
                @php $day = $col['day']; @endphp
                <div class="kw-week__col @if ($day->today) is-today @endif @if ($day->weekend) is-weekend @endif" data-kw-part="week-day-col" role="gridcell" data-kw-timecol="{{ $day->iso }}" wire:key="col-{{ $day->iso }}" @if ($canCreate) data-kw-creatable="1" @endif>
                    @foreach ($hours as $h)
                        <div class="kw-week__slot" data-kw-part="week-hour-slot"></div>
                    @endforeach
                    @if ($day->today)
                        @php $nowLocal = \Carbon\Carbon::now($tz ?? config('calendar.timezone', 'UTC')); @endphp
                        <div class="kw-week__now" data-kw-part="week-now-indicator" style="top: {{ round(($nowLocal->hour * 60 + $nowLocal->minute) / 1440 * 100, 3) }}%" aria-hidden="true"></div>
                    @endif
                    @foreach ($col['timed'] as $card)
                        @php
                            $lane = (int) ($card['lane'] ?? 0);
                            $lanes = (int) ($card['lanes'] ?? 1);
                        @endphp
                        <div
                            class="kw-week__event @if ($lanes > 1) is-multi-lane @endif @if ($lanes >= 3) is-cascaded @endif"
                            data-kw-part="week-event-wrapper"
                            style="top: {{ $card['top'] }}%; height: {{ $card['height'] }}%; inset-inline-start: {{ $card['left'] }}%; width: {{ $card['width'] }}%; --kw-lane: {{ $lane }}"
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
