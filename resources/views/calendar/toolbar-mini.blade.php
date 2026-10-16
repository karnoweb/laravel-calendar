<div class="kw-calendar__toolbar kw-calendar__toolbar--mini navbar" data-kw-part="toolbar-mini">
    <h2 class="kw-calendar__title" data-kw-part="toolbar-title" aria-live="polite">{{ $title }}</h2>
    <div class="kw-calendar__nav" data-kw-part="toolbar-nav">
        <button type="button" class="kw-btn kw-btn--today-mini btn btn-xs btn-soft btn-primary" data-kw-part="today-btn" data-kw-action="today" wire:click="today">{{ $t('toolbar.today') }}</button>
        <button type="button" class="kw-btn kw-btn--icon btn btn-xs btn-ghost btn-square" data-kw-part="prev-btn" data-kw-action="previous" wire:click="previous" aria-label="{{ $t('toolbar.previous') }}"><svg class="kw-chev" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <button type="button" class="kw-btn kw-btn--icon btn btn-xs btn-ghost btn-square" data-kw-part="next-btn" data-kw-action="next" wire:click="next" aria-label="{{ $t('toolbar.next') }}"><svg class="kw-chev kw-chev--next" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
    </div>
</div>
