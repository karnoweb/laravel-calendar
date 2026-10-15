{{-- $kind: conflict | unknown. Data on screen is already the freshly loaded authoritative state. --}}
<div class="kw-state kw-state--{{ $kind }}" role="alert">
    <strong>{{ $kind === 'conflict' ? $t('states.conflict_title') : $t('states.unknown_title') }}</strong>
    <p>{{ $kind === 'conflict' ? $t('states.conflict_body') : $t('states.unknown_body') }}</p>
    <button type="button" class="kw-btn" wire:click="dismissToast">{{ $t('states.reload') }}</button>
</div>
