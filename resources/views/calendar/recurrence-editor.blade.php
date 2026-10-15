{{-- Recurrence part of the event form. Values live in form.recurrence*. --}}
@php
    $freq = (string) ($form['recurrenceFreq'] ?? '');
    $endMode = (string) ($form['recurrenceEnd'] ?? 'never');
    $unit = ['DAILY' => 'daily', 'WEEKLY' => 'weekly', 'MONTHLY' => 'monthly', 'YEARLY' => 'yearly'];
@endphp
<fieldset class="kw-recurrence" data-kw-recurrence>
    <legend>{{ $t('recurrence.title') }}</legend>
    <div class="kw-field @if (! empty($formErrors['recurrenceFreq'])) has-error @endif">
        <select id="kw-f-recurrenceFreq" class="kw-input" wire:model.live="form.recurrenceFreq" aria-label="{{ $t('recurrence.title') }}">
            <option value="">{{ $t('recurrence.none') }}</option>
            @foreach ($unit as $value => $key)
                <option value="{{ $value }}">{{ $t('recurrence.'.$key) }}</option>
            @endforeach
        </select>
        @if (! empty($formErrors['recurrenceFreq']))<small class="kw-field__error" role="alert">{{ $formErrors['recurrenceFreq'] }}</small>@endif
    </div>

    @if ($freq !== '')
        <div class="kw-field">
            <label class="kw-field__label" for="kw-f-recurrenceInterval">{{ $t('recurrence.every') }}</label>
            <input id="kw-f-recurrenceInterval" class="kw-input" type="number" min="1" max="99" wire:model="form.recurrenceInterval">
        </div>

        @if ($freq === 'WEEKLY')
            <div class="kw-field">
                <span class="kw-field__label">{{ $t('recurrence.weekdays') }}</span>
                <div class="kw-weekday-picker" role="group">
                    @foreach ($weekdayNames as $wd)
                        <label class="kw-check kw-check--pill">
                            <input type="checkbox" value="{{ $wd['n'] }}" wire:model="form.recurrenceWeekdays">
                            <span>{{ $wd['short'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="kw-field">
            <label class="kw-field__label" for="kw-f-recurrenceEnd">{{ $t('recurrence.ends') }}</label>
            <select id="kw-f-recurrenceEnd" class="kw-input" wire:model.live="form.recurrenceEnd">
                <option value="never">{{ $t('recurrence.never') }}</option>
                <option value="count">{{ $t('recurrence.count') }}</option>
                <option value="until">{{ $t('recurrence.until') }}</option>
            </select>
        </div>

        @if ($endMode === 'count')
            <div class="kw-field @if (! empty($formErrors['recurrenceCount'])) has-error @endif">
                <label class="kw-field__label" for="kw-f-recurrenceCount">{{ $t('recurrence.count_label') }}</label>
                <input id="kw-f-recurrenceCount" class="kw-input" type="number" min="1" wire:model="form.recurrenceCount">
                @if (! empty($formErrors['recurrenceCount']))<small class="kw-field__error" role="alert">{{ $formErrors['recurrenceCount'] }}</small>@endif
            </div>
        @elseif ($endMode === 'until')
            <div class="kw-field @if (! empty($formErrors['recurrenceUntil'])) has-error @endif">
                <label class="kw-field__label" for="kw-f-recurrenceUntil">{{ $t('recurrence.until_label') }}</label>
                <input id="kw-f-recurrenceUntil" class="kw-input kw-input--mono" dir="ltr" inputmode="numeric" placeholder="YYYY/MM/DD" wire:model="form.recurrenceUntil">
                <small class="kw-field__hint">{{ $t('form.date_hint') }}</small>
                @if (! empty($formErrors['recurrenceUntil']))<small class="kw-field__error" role="alert">{{ $formErrors['recurrenceUntil'] }}</small>@endif
            </div>
        @endif
    @endif
</fieldset>
