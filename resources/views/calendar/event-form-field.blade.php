@php
    use Karnoweb\Calendar\Support\CalendarTranslations as Tr;
    $name = $field['name'];
    $id = 'kw-f-'.$name;
    $err = $formErrors[$name] ?? null;
    $label = Tr::label($field['label'], $locale);
    $type = $field['type'];
    $hint = $type === 'date' ? $t('form.date_hint') : ($type === 'time' ? $t('form.time_hint') : null);
    if ($name === 'endDate' && ($form['allDay'] ?? false)) {
        $hint = $t('form.all_day_end_hint');
    }
@endphp
<div class="kw-field @if ($err) has-error @endif" data-kw-part="form-field" data-kw-field="{{ $name }}" wire:key="field-{{ $name }}">
    @if ($type === 'checkbox')
        <label class="kw-check label cursor-pointer justify-start" for="{{ $id }}">
            <input id="{{ $id }}" class="checkbox checkbox-sm checkbox-primary" type="checkbox" wire:model.live="form.{{ $name }}">
            <span>{{ $label }}</span>
        </label>
    @else
        <label class="kw-field__label label" for="{{ $id }}">{{ $label }}@if (! empty($field['required'])) <span aria-hidden="true">*</span>@endif</label>
        @if ($type === 'textarea')
            <textarea id="{{ $id }}" class="kw-input textarea textarea-sm" rows="3" maxlength="{{ $field['max'] ?? 5000 }}" wire:model="form.{{ $name }}" @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif></textarea>
        @elseif ($type === 'select')
            <select id="{{ $id }}" class="kw-input select select-sm" wire:model="form.{{ $name }}" @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif>
                @foreach (($field['options'] ?? []) as $value => $optLabel)
                    <option value="{{ $value }}">{{ Tr::label((string) $optLabel, $locale) }}</option>
                @endforeach
            </select>
        @elseif (in_array($type, ['date', 'time'], true))
            <div class="kw-input-picker" data-kw-picker-wrap data-kw-part="picker-wrap">
                <input
                    id="{{ $id }}"
                    class="kw-input input input-sm kw-input--mono"
                    type="text"
                    data-kw-picker="{{ $type }}"
                    inputmode="numeric"
                    autocomplete="off"
                    dir="ltr"
                    placeholder="{{ $type === 'date' ? 'YYYY/MM/DD' : 'HH:MM' }}"
                    @if (! empty($field['max'])) maxlength="{{ $field['max'] }}" @endif
                    wire:model="form.{{ $name }}"
                    @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif
                >
                <button type="button" class="kw-picker-trigger btn btn-xs btn-ghost btn-square" data-kw-picker-btn="{{ $type }}" data-kw-part="picker-trigger" data-kw-action="open-{{ $type }}-picker" tabindex="-1" aria-label="{{ $label }}">
                    @if ($type === 'date')
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    @endif
                </button>
            </div>
        @else
            <input
                id="{{ $id }}"
                class="kw-input input input-sm"
                type="{{ $type === 'number' ? 'number' : 'text' }}"
                @if (! empty($field['max'])) maxlength="{{ $field['max'] }}" @endif
                wire:model="form.{{ $name }}"
                @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif
            >
        @endif
        @if ($hint)<small class="kw-field__hint">{{ $hint }}</small>@endif
    @endif
    @if ($err)<small id="{{ $id }}-err" class="kw-field__error" role="alert">{{ $err }}</small>@endif
</div>
