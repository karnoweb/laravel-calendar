@php $kind = $pendingView['kind'] ?? 'delete'; @endphp
<div class="kw-modal" data-kw-modal wire:keydown.escape="cancelPending">
    <div class="kw-modal__backdrop" wire:click="cancelPending"></div>
    <div class="kw-dialog kw-dialog--small" role="alertdialog" aria-modal="true" aria-labelledby="kw-confirm-title" aria-describedby="kw-confirm-body">
        <header class="kw-dialog__head"><h3 id="kw-confirm-title">{{ $t('confirm.'.$kind.'_title') }}</h3></header>
        <div class="kw-dialog__body"><p id="kw-confirm-body">{{ $t('confirm.'.$kind.'_body') }}</p></div>
        <footer class="kw-dialog__foot">
            <button type="button" class="kw-btn" wire:click="cancelPending" data-kw-autofocus>{{ $t('confirm.cancel') }}</button>
            <button type="button" class="kw-btn {{ $kind === 'delete' ? 'kw-btn--danger' : 'kw-btn--primary' }}" wire:click="confirmPending" wire:loading.attr="disabled" wire:target="confirmPending">{{ $t('confirm.confirm') }}</button>
        </footer>
    </div>
</div>
