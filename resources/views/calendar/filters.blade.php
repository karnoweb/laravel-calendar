@php
    use Karnoweb\Calendar\Support\CalendarTranslations as Tr;
@endphp
@if (count($sources) > 0 || count($filtersUi) > 0)
    <div class="kw-calendar__filters" role="group" aria-label="{{ $t('toolbar.filters') }}">
        <h3 class="kw-side__title">{{ $t('toolbar.filters') }}</h3>
        @foreach ($sources as $source)
            <button
                type="button"
                class="kw-chip {{ $source['colorClass'] }} @if ($source['enabled']) is-on @endif @if ($source['failed']) is-failed @endif"
                style="{{ $source['colorStyle'] }}"
                aria-pressed="{{ $source['enabled'] ? 'true' : 'false' }}"
                wire:click="toggleSource('{{ $source['key'] }}')"
                wire:key="src-{{ $source['key'] }}"
            >
                <span class="kw-chip__dot" aria-hidden="true"></span>{{ Tr::label($source['label'], $locale) }}
            </button>
        @endforeach

        @foreach ($filtersUi as $filter)
            <label class="kw-filter" wire:key="flt-{{ $filter['key'] }}">
                <span class="kw-sr">{{ Tr::label($filter['label'], $locale) }}</span>
                <select class="kw-input" aria-label="{{ Tr::label($filter['label'], $locale) }}" wire:change="setFilter('{{ $filter['key'] }}', $event.target.value)">
                    <option value="">{{ Tr::label($filter['label'], $locale) }}: {{ $t('toolbar.all') }}</option>
                    @foreach ($filter['options'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) $filter['selected'] === (string) $value)>{{ Tr::label((string) $label, $locale) }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach

        @if ($hasActiveFilters)
            <button type="button" class="kw-btn kw-btn--ghost" wire:click="resetFilters">{{ $t('toolbar.reset_filters') }}</button>
        @endif
    </div>
@endif
