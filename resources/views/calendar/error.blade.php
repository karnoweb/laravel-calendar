{{-- $kind: denied | rate_limited | error. Never shows internal details. --}}
<div class="kw-state kw-state--{{ $kind }} alert alert-error" data-kw-part="error-state" role="alert">
    <p>
        @if ($kind === 'denied')
            {{ $t('states.denied') }}
        @elseif ($kind === 'rate_limited')
            {{ $t('messages.rate_limited') }}
        @else
            {{ $t('states.error') }}
        @endif
    </p>
    @if ($kind !== 'denied')
        <button type="button" class="kw-btn btn btn-sm btn-outline" data-kw-part="retry-btn" data-kw-action="retry" wire:click="refreshNow">{{ $t('states.retry') }}</button>
    @endif
</div>
