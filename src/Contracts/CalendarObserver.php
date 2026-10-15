<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Contracts;

/** Receives observations that NEVER contain event titles/descriptions or other private text. */
interface CalendarObserver
{
    /** @param array<string, scalar|null> $data */
    public function observe(string $name, array $data): void;
}
