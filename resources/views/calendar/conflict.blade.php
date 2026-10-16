{{-- $kind: conflict | unknown. Data on screen is already the freshly loaded authoritative state. --}}
<div class="kw-state kw-state--{{ $kind }} alert alert-warning" data-kw-part="conflict-state" role="alert">
    <strong>{{ $kind === 'conflict' ? $t('states.conflict_title') : $t('states.unknown_title') }}</strong>
    <p>{{ $kind === 'conflict' ? $t('states.conflict_body') : $t('states.unknown_body') }}</p>
    <button type="button" class="kw-btn btn btn-sm btn-outline" data-kw-part="reload-btn" data-kw-action="reload" wire:click="dismissToast">{{ $t('states.reload') }}</button>
</div>
