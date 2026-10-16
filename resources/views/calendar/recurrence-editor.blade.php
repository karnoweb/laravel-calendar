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
                <div class="kw-input-picker" data-kw-picker-wrap data-kw-part="picker-wrap">
                    <input id="kw-f-recurrenceUntil" class="kw-input kw-input--mono input input-sm" data-kw-picker="date" dir="ltr" inputmode="numeric" autocomplete="off" placeholder="YYYY/MM/DD" wire:model="form.recurrenceUntil">
                    <button type="button" class="kw-picker-trigger btn btn-xs btn-ghost btn-square" data-kw-picker-btn="date" data-kw-part="picker-trigger" data-kw-action="open-date-picker" tabindex="-1" aria-label="{{ $t('recurrence.until_label') }}">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </button>
                </div>
                <small class="kw-field__hint">{{ $t('form.date_hint') }}</small>
                @if (! empty($formErrors['recurrenceUntil']))<small class="kw-field__error" role="alert">{{ $formErrors['recurrenceUntil'] }}</small>@endif
            </div>
        @endif
    @endif
</fieldset>
