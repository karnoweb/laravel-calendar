<div class="kw-pagination" data-kw-part="pagination">
    @if ($read->truncated)
        <span class="kw-pagination__note" data-kw-part="pagination-note">{{ $t('states.truncated') }}</span>
    @endif
    @if ($read->hasMore)
        <button type="button" class="kw-btn btn btn-sm btn-outline" data-kw-part="load-more-btn" data-kw-action="load-more" wire:click="{{ $paginationAction ?? 'loadMore' }}" wire:loading.attr="disabled">{{ $t('states.load_more') }}</button>
    @endif
</div>
