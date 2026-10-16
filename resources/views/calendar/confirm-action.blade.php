@php $kind = $pendingView['kind'] ?? 'delete'; @endphp
<div class="kw-modal modal modal-open" data-kw-modal data-kw-part="confirm-modal" wire:keydown.escape="cancelPending">
    <div class="kw-modal__backdrop modal-backdrop" data-kw-part="modal-backdrop" data-kw-action="cancel-confirm" wire:click="cancelPending"></div>
    <div class="kw-dialog kw-dialog--small modal-box" data-kw-part="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="kw-confirm-title" aria-describedby="kw-confirm-body">
        <header class="kw-dialog__head" data-kw-part="dialog-header"><h3 id="kw-confirm-title" data-kw-part="dialog-title">{{ $t('confirm.'.$kind.'_title') }}</h3></header>
        <div class="kw-dialog__body" data-kw-part="dialog-body"><p id="kw-confirm-body">{{ $t('confirm.'.$kind.'_body') }}</p></div>
        <footer class="kw-dialog__foot modal-action" data-kw-part="dialog-footer">
            <button type="button" class="kw-btn btn btn-sm btn-ghost" data-kw-part="confirm-cancel-btn" data-kw-action="cancel-confirm" wire:click="cancelPending" data-kw-autofocus>{{ $t('confirm.cancel') }}</button>
            <button type="button" class="kw-btn btn btn-sm {{ $kind === 'delete' ? 'kw-btn--danger btn-error' : 'kw-btn--primary btn-primary' }}" data-kw-part="confirm-submit-btn" data-kw-action="confirm-pending" wire:click="confirmPending" wire:loading.attr="disabled" wire:target="confirmPending">{{ $t('confirm.confirm') }}</button>
        </footer>
    </div>
</div>
