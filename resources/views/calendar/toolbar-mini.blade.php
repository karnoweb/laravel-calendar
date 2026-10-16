<div class="kw-calendar__toolbar kw-calendar__toolbar--mini">
    <h2 class="kw-calendar__title" aria-live="polite">{{ $title }}</h2>
    <div class="kw-calendar__nav">
        <button type="button" class="kw-btn kw-btn--today-mini" wire:click="today">{{ $t('toolbar.today') }}</button>
        <button type="button" class="kw-btn kw-btn--icon" wire:click="previous" aria-label="{{ $t('toolbar.previous') }}"><svg class="kw-chev" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <button type="button" class="kw-btn kw-btn--icon" wire:click="next" aria-label="{{ $t('toolbar.next') }}"><svg class="kw-chev kw-chev--next" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
    </div>
</div>
