@php $intent = $scopePromptView['intent'] ?? 'edit'; @endphp
<div class="kw-modal" data-kw-modal wire:keydown.escape="cancelScope">
    <div class="kw-modal__backdrop" wire:click="cancelScope"></div>
    <div class="kw-dialog kw-dialog--small" role="alertdialog" aria-modal="true" aria-labelledby="kw-scope-title">
        <header class="kw-dialog__head"><h3 id="kw-scope-title">{{ $t('recurrence.scope_title') }}</h3></header>
        <div class="kw-dialog__body">
            <p>{{ $t($intent === 'delete' ? 'recurrence.scope_delete' : 'recurrence.scope_edit') }}</p>
            <div class="kw-stack">
                <button type="button" class="kw-btn" wire:click="chooseScope('this')" data-kw-autofocus>{{ $t('recurrence.this') }}</button>
                <button type="button" class="kw-btn" wire:click="chooseScope('future')">{{ $t('recurrence.future') }}</button>
                <button type="button" class="kw-btn" wire:click="chooseScope('all')">{{ $t('recurrence.all') }}</button>
            </div>
        </div>
        <footer class="kw-dialog__foot">
            <button type="button" class="kw-btn" wire:click="cancelScope">{{ $t('confirm.cancel') }}</button>
        </footer>
    </div>
</div>
