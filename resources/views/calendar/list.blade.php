@if ($listRowCount === 0)
    @include($views['empty'])
@else
    <div
        class="kw-list"
        data-kw-virtual-list
        data-row-height="{{ $listRowHeight }}"
        data-count="{{ $listRowCount }}"
        data-rows="{{ $listRowsJson }}"
        role="list"
        aria-label="{{ $title }}"
        tabindex="0"
    >
        <div class="kw-list__spacer" data-kw-spacer style="height: {{ $listRowCount * $listRowHeight }}px"><div wire:ignore data-kw-rowhost></div></div>
    </div>
    <p class="kw-sr" role="status">{{ $t('states.events_count', ['count' => $digits($listLoaded)]) }}</p>
    @if ($read->hasMore)
        @include('calendar::calendar.pagination', ['paginationAction' => 'loadMore'])
    @endif
@endif
