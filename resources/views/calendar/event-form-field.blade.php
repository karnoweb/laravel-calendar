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
<div class="kw-field @if ($err) has-error @endif" wire:key="field-{{ $name }}">
    @if ($type === 'checkbox')
        <label class="kw-check" for="{{ $id }}">
            <input id="{{ $id }}" type="checkbox" wire:model.live="form.{{ $name }}">
            <span>{{ $label }}</span>
        </label>
    @else
        <label class="kw-field__label" for="{{ $id }}">{{ $label }}@if (! empty($field['required'])) <span aria-hidden="true">*</span>@endif</label>
        @if ($type === 'textarea')
            <textarea id="{{ $id }}" class="kw-input" rows="3" maxlength="{{ $field['max'] ?? 5000 }}" wire:model="form.{{ $name }}" @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif></textarea>
        @elseif ($type === 'select')
            <select id="{{ $id }}" class="kw-input" wire:model="form.{{ $name }}" @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif>
                @foreach (($field['options'] ?? []) as $value => $optLabel)
                    <option value="{{ $value }}">{{ Tr::label((string) $optLabel, $locale) }}</option>
                @endforeach
            </select>
        @else
            <input
                id="{{ $id }}"
                class="kw-input @if (in_array($type, ['date', 'time'], true)) kw-input--mono @endif"
                type="{{ $type === 'number' ? 'number' : 'text' }}"
                @if (in_array($type, ['date', 'time'], true)) inputmode="numeric" autocomplete="off" dir="ltr" placeholder="{{ $type === 'date' ? 'YYYY/MM/DD' : 'HH:MM' }}" @endif
                @if (! empty($field['max'])) maxlength="{{ $field['max'] }}" @endif
                wire:model="form.{{ $name }}"
                @if ($err) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif
            >
        @endif
        @if ($hint)<small class="kw-field__hint">{{ $hint }}</small>@endif
    @endif
    @if ($err)<small id="{{ $id }}-err" class="kw-field__error" role="alert">{{ $err }}</small>@endif
</div>
