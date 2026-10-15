{{-- Receives $card. Hover/focus summary; purely presentational. --}}
<span class="kw-popover" role="tooltip">
    <strong>{{ $card['event']->title }}</strong>
    @if ($card['timeRange'])<span>{{ $card['timeRange'] }}</span>@endif
    @if ($card['event']->location)<span>{{ $card['event']->location }}</span>@endif
</span>
