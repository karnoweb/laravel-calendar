@php $hasAny = $read->events !== []; @endphp
<div class="kw-month" data-kw-part="month-view" role="grid" aria-label="{{ $title }}" aria-rowcount="{{ count($monthRows) + 1 }}">
    <div class="kw-month__head" data-kw-part="month-header" role="row">
        @foreach ($weekdayNames as $wd)
            <div class="kw-month__weekday @if (in_array($wd['n'], $weekendDays, true)) is-weekend @endif" data-kw-part="month-weekday" role="columnheader">
                <span class="kw-month__wd-long">{{ $wd['long'] }}</span>
                <abbr class="kw-month__wd-short" title="{{ $wd['long'] }}">{{ $wd['short'] }}</abbr>
            </div>
        @endforeach
    </div>
    @foreach ($monthRows as $ri => $row)
        <div class="kw-month__row" data-kw-part="month-row" role="row" wire:key="row-{{ $ri }}" style="--kw-span-lanes: {{ $row['spanLanes'] }}">
            @foreach ($row['cells'] as $cell)
                @include('calendar::calendar.month-day', ['cell' => $cell])
            @endforeach
            @if ($row['spans'] !== [])
                <div class="kw-month__spans" data-kw-part="month-spans" role="presentation">
                    @foreach ($row['spans'] as $si => $span)
                        @include('calendar::calendar.month-span', ['span' => $span, 'si' => $si, 'ri' => $ri])
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
@if (($miniMonth ?? false) && count($sources) > 0)
    <div class="kw-mini-legend" data-kw-part="mini-legend" aria-label="{{ $t('toolbar.sources') }}">
        @foreach ($sources as $src)
            <span class="kw-mini-legend__item {{ $src['colorClass'] }}" data-kw-part="mini-legend-item" style="{{ $src['colorStyle'] }}">
                <span class="kw-mini-legend__dot" aria-hidden="true"></span>
                <span class="kw-mini-legend__text">{{ \Karnoweb\Calendar\Support\CalendarTranslations::label($src['label'], $locale) }}</span>
            </span>
        @endforeach
    </div>
@endif
@if (! $hasAny && ! ($miniMonth ?? false))
    @include($views['empty'])
@endif
