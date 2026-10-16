{{-- Receives $card: the event card plus when/canEdit/canDelete. Text is always escaped. --}}
@php
    use Karnoweb\Calendar\Support\CalendarTranslations as Tr;
    $e = $card['event'];
    $taskMovable = $card['taskMovable'] ?? $card['movable'];
@endphp
<div class="kw-modal kw-modal--drawer modal modal-open" data-kw-modal data-kw-part="details-modal" wire:keydown.escape="closeDetails">
    <div class="kw-modal__backdrop modal-backdrop" data-kw-part="modal-backdrop" data-kw-action="close-details" wire:click="closeDetails"></div>
    <aside class="kw-drawer modal-box" data-kw-part="details-drawer" role="dialog" aria-modal="true" aria-labelledby="kw-details-title" tabindex="-1">
        <header class="kw-drawer__head {{ $card['colorClass'] }}" data-kw-part="drawer-header" style="{{ $card['colorStyle'] }}">
            <h3 id="kw-details-title" data-kw-part="drawer-title">{{ $e->title }}</h3>
            <button type="button" class="kw-btn kw-btn--icon btn btn-sm btn-ghost btn-square" data-kw-part="drawer-close" data-kw-action="close-details" wire:click="closeDetails" aria-label="{{ $t('details.close') }}" data-kw-autofocus>×</button>
        </header>
        <div class="kw-drawer__body" data-kw-part="drawer-body">
            <dl class="kw-details" data-kw-part="details-list">
                <dt>{{ $t('details.calendar') }}</dt>
                <dd>{{ Tr::label($card['source']->label, $locale) }}</dd>
                <dt>{{ $t('details.when') }}</dt>
                <dd>{{ $card['when'] }}@if ($e->allDay) ({{ $t('details.all_day') }})@endif</dd>
                @if ($e->location)
                    <dt>{{ $t('details.where') }}</dt>
                    <dd>{{ $e->location }}</dd>
                @endif
                @if ($e->description)
                    <dd class="kw-details__desc" data-kw-part="details-description">{{ $e->description }}</dd>
                @endif
                @if ($e->isRecurring)
                    <dd><span class="kw-badge badge badge-sm badge-soft" data-kw-part="recurring-badge">{{ $t('details.recurring') }}</span></dd>
                @endif
                @if ($card['readOnly'])
                    <dd><span class="kw-badge kw-badge--muted badge badge-sm badge-ghost" data-kw-part="readonly-badge">{{ $t('details.read_only') }}</span></dd>
                @endif
                @if ($e->url)
                    <dd><a href="{{ $e->url }}" class="link link-primary" data-kw-part="details-link" rel="noopener noreferrer nofollow" target="_blank">{{ $t('details.open_link') }}</a></dd>
                @endif
            </dl>

            @if (! $card['source']->readOnly)
                <div class="kw-drawer__section" data-kw-part="details-movability-section">
                    <div class="kw-drawer__row">
                        <div>
                            <div class="kw-drawer__section-title">{{ $t('details.movable_label') }}</div>
                            <span class="kw-badge badge badge-sm @if ($taskMovable) kw-badge--ok badge-soft badge-success @else kw-badge--locked badge-soft badge-error @endif" data-kw-part="movable-status-badge">
                                {{ $taskMovable ? $t('details.movable_on') : $t('details.movable_off') }}
                            </span>
                        </div>
                        @if ($card['canToggleMovable'] ?? false)
                            <button
                                type="button"
                                class="kw-btn kw-btn--sm btn btn-xs btn-outline"
                                data-kw-toggle-task-movable
                                data-kw-part="toggle-task-movable-btn"
                                data-kw-action="toggle-event-movable"
                                wire:click="toggleEventMovable('{{ $card['ref'] }}', '{{ $card['version'] }}')"
                            >
                                {{ $taskMovable ? $t('details.lock_move') : $t('details.unlock_move') }}
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            @if ($card['movable'])
                <div class="kw-drawer__section" data-kw-part="details-quick-move-section">
                    <div class="kw-drawer__section-title">{{ $t('details.quick_move') }}</div>
                    <div class="kw-quick-move" data-kw-part="quick-move-form">
                        <label class="kw-field">
                            <span class="kw-field__label label">{{ $t('form.start_date') }}</span>
                            <div class="kw-input-picker" data-kw-picker-wrap data-kw-part="picker-wrap">
                                <input type="text" class="kw-input kw-input--mono input input-sm" data-kw-part="quick-move-date" data-kw-picker="date" inputmode="numeric" autocomplete="off" dir="ltr" placeholder="YYYY/MM/DD" wire:model="quickMoveDate">
                                <button type="button" class="kw-picker-trigger btn btn-xs btn-ghost btn-square" data-kw-picker-btn="date" data-kw-part="picker-trigger" data-kw-action="open-date-picker" tabindex="-1" aria-label="{{ $t('form.start_date') }}">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </button>
                            </div>
                        </label>
                        @if (! $e->allDay)
                            <label class="kw-field">
                                <span class="kw-field__label label">{{ $t('form.start_time') }}</span>
                                <div class="kw-input-picker" data-kw-picker-wrap data-kw-part="picker-wrap">
                                    <input type="text" class="kw-input kw-input--mono input input-sm" data-kw-part="quick-move-time" data-kw-picker="time" inputmode="numeric" autocomplete="off" dir="ltr" placeholder="HH:MM" wire:model="quickMoveTime">
                                    <button type="button" class="kw-picker-trigger btn btn-xs btn-ghost btn-square" data-kw-picker-btn="time" data-kw-part="picker-trigger" data-kw-action="open-time-picker" tabindex="-1" aria-label="{{ $t('form.start_time') }}">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </button>
                                </div>
                            </label>
                        @endif
                        <button
                            type="button"
                            class="kw-btn kw-btn--primary kw-quick-move__btn btn btn-sm btn-primary"
                            data-kw-quick-move
                            data-kw-part="quick-move-submit"
                            data-kw-action="quick-move"
                            wire:click="quickMove('{{ $card['ref'] }}', '{{ $card['version'] }}')"
                        >
                            {{ $t('details.apply_move') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>
        <footer class="kw-drawer__foot modal-action" data-kw-part="drawer-footer">
            @if ($card['canEdit'])
                <button type="button" class="kw-btn kw-btn--primary btn btn-sm btn-primary" data-kw-part="edit-event-btn" data-kw-action="edit-event" wire:click="openEdit('{{ $card['ref'] }}')">{{ $t('details.edit') }}</button>
            @endif
            @if ($card['canDelete'])
                <button type="button" class="kw-btn kw-btn--danger btn btn-sm btn-error" data-kw-part="delete-event-btn" data-kw-action="delete-event" wire:click="requestDelete('{{ $card['ref'] }}', '{{ $card['version'] }}')">{{ $t('details.delete') }}</button>
            @endif
            @if (! $card['canEdit'] && ! $card['canDelete'])
                <span class="kw-muted">{{ $t('details.no_actions') }}</span>
            @endif
        </footer>
    </aside>
</div>
