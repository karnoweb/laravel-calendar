@php $intent = $scopePromptView['intent'] ?? 'edit'; @endphp
<div class="kw-modal modal modal-open" data-kw-modal data-kw-part="scope-modal" wire:keydown.escape="cancelScope">
    <div class="kw-modal__backdrop modal-backdrop" data-kw-part="modal-backdrop" data-kw-action="cancel-scope" wire:click="cancelScope"></div>
    <div class="kw-dialog kw-dialog--small modal-box" data-kw-part="scope-dialog" role="alertdialog" aria-modal="true" aria-labelledby="kw-scope-title">
        <header class="kw-dialog__head" data-kw-part="dialog-header"><h3 id="kw-scope-title" data-kw-part="dialog-title">{{ $t('recurrence.scope_title') }}</h3></header>
        <div class="kw-dialog__body" data-kw-part="dialog-body">
            <p>{{ $t($intent === 'delete' ? 'recurrence.scope_delete' : 'recurrence.scope_edit') }}</p>
            <div class="kw-stack" data-kw-part="scope-options">
                <button type="button" class="kw-btn btn btn-sm btn-outline" data-kw-part="scope-this-btn" data-kw-action="choose-scope" data-kw-scope="this" wire:click="chooseScope('this')" data-kw-autofocus>{{ $t('recurrence.this') }}</button>
                <button type="button" class="kw-btn btn btn-sm btn-outline" data-kw-part="scope-future-btn" data-kw-action="choose-scope" data-kw-scope="future" wire:click="chooseScope('future')">{{ $t('recurrence.future') }}</button>
                <button type="button" class="kw-btn btn btn-sm btn-outline" data-kw-part="scope-all-btn" data-kw-action="choose-scope" data-kw-scope="all" wire:click="chooseScope('all')">{{ $t('recurrence.all') }}</button>
            </div>
        </div>
        <footer class="kw-dialog__foot modal-action" data-kw-part="dialog-footer">
            <button type="button" class="kw-btn btn btn-sm btn-ghost" data-kw-part="scope-cancel-btn" data-kw-action="cancel-scope" wire:click="cancelScope">{{ $t('confirm.cancel') }}</button>
        </footer>
    </div>
</div>
