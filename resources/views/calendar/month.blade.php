@php $hasAny = $read->events !== []; @endphp
<div class="kw-month" role="grid" aria-label="{{ $title }}" aria-rowcount="{{ count($monthRows) + 1 }}">
    <div class="kw-month__head" role="row">
        @foreach ($weekdayNames as $wd)
            <div class="kw-month__weekday @if (in_array($wd['n'], $weekendDays, true)) is-weekend @endif" role="columnheader"><abbr title="{{ $wd['long'] }}">{{ $wd['short'] }}</abbr></div>
        @endforeach
    </div>
    @foreach ($monthRows as $ri => $row)
        <div class="kw-month__row" role="row" wire:key="row-{{ $ri }}">
            @foreach ($row as $cell)
                @include('calendar::calendar.month-day', ['cell' => $cell])
            @endforeach
        </div>
    @endforeach
</div>
@if (! $hasAny)
    @include($views['empty'])
@endif
