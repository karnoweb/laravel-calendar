@php use Karnoweb\Calendar\Support\CalendarTranslations as Tr; @endphp
<div class="kw-modal" data-kw-modal wire:keydown.escape="closeForm">
    <div class="kw-modal__backdrop" wire:click="closeForm"></div>
    <form class="kw-dialog" role="dialog" aria-modal="true" aria-labelledby="kw-form-title" wire:submit.prevent="submitForm" novalidate>
        <header class="kw-dialog__head">
            <h3 id="kw-form-title">{{ $t($formMode === 'edit' ? 'form.edit_event' : 'form.new_event') }}</h3>
        </header>

        <div class="kw-dialog__body">
            @if (count($writableSources) > 1 && $formMode === 'create')
                <label class="kw-field">
                    <span class="kw-field__label">{{ $t('form.source') }}</span>
                    <select class="kw-input" wire:model.live="formSource">
                        @foreach ($writableSources as $ws)
                            <option value="{{ $ws['key'] }}">{{ Tr::label($ws['label'], $locale) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @foreach ($formSchema->fields as $field)
                @if (in_array($field['name'], ['startTime', 'endTime'], true) && ($form['allDay'] ?? false))
                    @continue
                @endif
                @include('calendar::calendar.event-form-field', ['field' => $field])
                @if (! empty($formOffsets[$field['name'] === 'startTime' ? 'startOffset' : 'endOffset']) && in_array($field['name'], ['startTime', 'endTime'], true))
                    @php $offsetKey = $field['name'] === 'startTime' ? 'startOffset' : 'endOffset'; @endphp
                    <label class="kw-field">
                        <span class="kw-field__label">{{ $t('validation.dst_ambiguous') }}</span>
                        <select class="kw-input" wire:model="form.{{ $offsetKey }}">
                            <option value="">—</option>
                            @foreach ($formOffsets[$offsetKey] as $offset)
                                <option value="{{ $offset }}">UTC{{ $offset }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            @endforeach

            @if ($formSchema->supportsRecurrence && ($formMode === 'create' || $formScope === null || $formScope !== 'this'))
                @include('calendar::calendar.recurrence-editor')
            @endif
        </div>

        <footer class="kw-dialog__foot">
            <button type="button" class="kw-btn" wire:click="closeForm">{{ $t('form.cancel') }}</button>
            <button type="submit" class="kw-btn kw-btn--primary" wire:loading.attr="disabled" wire:target="submitForm">
                <span wire:loading.remove wire:target="submitForm">{{ $t('form.save') }}</span>
                <span wire:loading wire:target="submitForm">{{ $t('form.saving') }}</span>
            </button>
        </footer>
    </form>
</div>
