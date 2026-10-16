{{-- Receives $card: the event card plus when/canEdit/canDelete. Text is always escaped. --}}
@php
    use Karnoweb\Calendar\Support\CalendarTranslations as Tr;
    $e = $card['event'];
    $taskMovable = $card['taskMovable'] ?? $card['movable'];
@endphp
<div class="kw-modal kw-modal--drawer" data-kw-modal wire:keydown.escape="closeDetails">
    <div class="kw-modal__backdrop" wire:click="closeDetails"></div>
    <aside class="kw-drawer" role="dialog" aria-modal="true" aria-labelledby="kw-details-title" tabindex="-1">
        <header class="kw-drawer__head {{ $card['colorClass'] }}" style="{{ $card['colorStyle'] }}">
            <h3 id="kw-details-title">{{ $e->title }}</h3>
            <button type="button" class="kw-btn kw-btn--icon" wire:click="closeDetails" aria-label="{{ $t('details.close') }}" data-kw-autofocus>×</button>
        </header>
        <div class="kw-drawer__body">
            <dl class="kw-details">
                <dt>{{ $t('details.calendar') }}</dt>
                <dd>{{ Tr::label($card['source']->label, $locale) }}</dd>
                <dt>{{ $t('details.when') }}</dt>
                <dd>{{ $card['when'] }}@if ($e->allDay) ({{ $t('details.all_day') }})@endif</dd>
                @if ($e->location)
                    <dt>{{ $t('details.where') }}</dt>
                    <dd>{{ $e->location }}</dd>
                @endif
                @if ($e->description)
                    <dd class="kw-details__desc">{{ $e->description }}</dd>
                @endif
                @if ($e->isRecurring)
                    <dd><span class="kw-badge">{{ $t('details.recurring') }}</span></dd>
                @endif
                @if ($card['readOnly'])
                    <dd><span class="kw-badge kw-badge--muted">{{ $t('details.read_only') }}</span></dd>
                @endif
                @if ($e->url)
                    <dd><a href="{{ $e->url }}" rel="noopener noreferrer nofollow" target="_blank">{{ $t('details.open_link') }}</a></dd>
                @endif
            </dl>

            @if (! $card['source']->readOnly)
                <div class="kw-drawer__section">
                    <div class="kw-drawer__row">
                        <div>
                            <div class="kw-drawer__section-title">{{ $t('details.movable_label') }}</div>
                            <span class="kw-badge @if ($taskMovable) kw-badge--ok @else kw-badge--locked @endif">
                                {{ $taskMovable ? $t('details.movable_on') : $t('details.movable_off') }}
                            </span>
                        </div>
                        @if ($card['canToggleMovable'] ?? false)
                            <button
                                type="button"
                                class="kw-btn kw-btn--sm"
                                data-kw-toggle-task-movable
                                wire:click="toggleEventMovable('{{ $card['ref'] }}', '{{ $card['version'] }}')"
                            >
                                {{ $taskMovable ? $t('details.lock_move') : $t('details.unlock_move') }}
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            @if ($card['movable'])
                <div class="kw-drawer__section">
                    <div class="kw-drawer__section-title">{{ $t('details.quick_move') }}</div>
                    <div class="kw-quick-move">
                        <label class="kw-field">
                            <span class="kw-field__label">{{ $t('form.start_date') }}</span>
                            <input type="text" class="kw-input kw-input--mono" dir="ltr" placeholder="YYYY/MM/DD" wire:model="quickMoveDate">
                        </label>
                        @if (! $e->allDay)
                            <label class="kw-field">
                                <span class="kw-field__label">{{ $t('form.start_time') }}</span>
                                <input type="text" class="kw-input kw-input--mono" dir="ltr" placeholder="HH:MM" wire:model="quickMoveTime">
                            </label>
                        @endif
                        <button
                            type="button"
                            class="kw-btn kw-btn--primary kw-quick-move__btn"
                            data-kw-quick-move
                            wire:click="quickMove('{{ $card['ref'] }}', '{{ $card['version'] }}')"
                        >
                            {{ $t('details.apply_move') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>
        <footer class="kw-drawer__foot">
            @if ($card['canEdit'])
                <button type="button" class="kw-btn kw-btn--primary" wire:click="openEdit('{{ $card['ref'] }}')">{{ $t('details.edit') }}</button>
            @endif
            @if ($card['canDelete'])
                <button type="button" class="kw-btn kw-btn--danger" wire:click="requestDelete('{{ $card['ref'] }}', '{{ $card['version'] }}')">{{ $t('details.delete') }}</button>
            @endif
            @if (! $card['canEdit'] && ! $card['canDelete'])
                <span class="kw-muted">{{ $t('details.no_actions') }}</span>
            @endif
        </footer>
    </aside>
</div>
