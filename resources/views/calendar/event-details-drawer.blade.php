{{-- Receives $card: the event card plus when/canEdit/canDelete. Text is always escaped. --}}
@php
    use Karnoweb\Calendar\Support\CalendarTranslations as Tr;
    $e = $card['event'];
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
