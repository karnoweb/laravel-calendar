<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use InvalidArgumentException;

/**
 * Composite cursor over several sources: {"task": "<providerCursor>", "class": "<providerCursor>"}.
 * Sources that are exhausted are simply absent. The encoded cursor is opaque and only ever issued
 * by the server; the browser/Livewire layer never accepts one from the client.
 */
final class CursorPaginationMerger
{
    /** @param array<string, string> $cursors */
    public static function encode(array $cursors): ?string
    {
        if ($cursors === []) {
            return null;
        }
        ksort($cursors);

        return rtrim(strtr(base64_encode((string) json_encode($cursors)), '+/', '-_'), '=');
    }

    /** @return array<string, string> */
    public static function decode(string $encoded): array
    {
        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);
        $data = $raw === false ? null : json_decode($raw, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException('Malformed cursor.');
        }
        $out = [];
        foreach ($data as $k => $v) {
            if (! is_string($k) || ! is_string($v) || $v === '') {
                throw new InvalidArgumentException('Malformed cursor.');
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
