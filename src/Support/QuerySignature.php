<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Data\ReadRequest;

/** Deterministic hash of a read request; used as requestKey so late replies can be recognised as stale. */
final class QuerySignature
{
    public static function for(string $calendarKey, ReadRequest $request): string
    {
        return substr(hash('sha256', (string) json_encode([
            $calendarKey, $request->range->signature(), $request->search, $request->filters,
            $request->sources, $request->pages,
        ])), 0, 24);
    }

    /** Signature of the visible navigation state (view+date+system+filters); used by the browser. */
    public static function ofState(string ...$parts): string
    {
        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }
}
