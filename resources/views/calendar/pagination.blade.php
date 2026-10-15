<div class="kw-pagination">
    @if ($read->truncated)
        <span class="kw-pagination__note">{{ $t('states.truncated') }}</span>
    @endif
    @if ($read->hasMore)
        <button type="button" class="kw-btn" wire:click="{{ $paginationAction ?? 'loadMore' }}" wire:loading.attr="disabled">{{ $t('states.load_more') }}</button>
    @endif
</div>
