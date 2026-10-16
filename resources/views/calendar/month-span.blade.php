@php
    $card = $span['card'];
    $continuesBefore = $span['continuesBefore'] ?? false;
    $continuesAfter = $span['continuesAfter'] ?? false;
@endphp
<div
    class="kw-month__span"
    style="--kw-col-start: {{ $span['colStart'] + 1 }}; --kw-col-span: {{ $span['colSpan'] }}; --kw-span-lane: {{ $span['lane'] + 1 }}"
    wire:key="sp-{{ $ri }}-{{ $si }}-{{ $card['ref'] }}"
>
    <div class="kw-month__span-inner @if ($continuesBefore) continues-before @endif @if ($continuesAfter) continues-after @endif">
        @include($views['event'], ['card' => $card, 'compact' => true, 'spanBar' => true, 'continuesBefore' => $continuesBefore, 'continuesAfter' => $continuesAfter])
    </div>
</div>
