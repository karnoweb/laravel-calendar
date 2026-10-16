@if ($listRowCount === 0)
    @include($views['empty'])
@else
    <div
        class="kw-list list"
        data-kw-virtual-list
        data-kw-part="list-view"
        data-row-height="{{ $listRowHeight }}"
        data-count="{{ $listRowCount }}"
        data-rows="{{ $listRowsJson }}"
        role="list"
        aria-label="{{ $title }}"
        tabindex="0"
    >
        <div class="kw-list__spacer" data-kw-spacer data-kw-part="list-spacer" style="height: {{ $listRowCount * $listRowHeight }}px"><div wire:ignore data-kw-rowhost data-kw-part="list-rowhost"></div></div>
    </div>
    <p class="kw-sr" role="status">{{ $t('states.events_count', ['count' => $digits($listLoaded)]) }}</p>
    @if ($read->hasMore)
        @include('calendar::calendar.pagination', ['paginationAction' => 'loadMore'])
    @endif
@endif
