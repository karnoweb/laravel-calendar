<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use InvalidArgumentException;
use Karnoweb\Calendar\Data\EventReference;

/**
 * Wire format for EventReference (DOM attributes, Livewire call arguments).
 * It is an opaque base64url(JSON [source, id, occurrence]) so no component ever splits an id on a prefix.
 * The decoded value is only a CLAIM: authorization is always re-established through the provider.
 */
final class EventReferenceCodec
{
    public static function encode(EventReference $reference): string
    {
        $json = json_encode([$reference->sourceKey, $reference->eventId, $reference->occurrenceKey], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public static function decode(string $encoded): EventReference
    {
        if ($encoded === '' || strlen($encoded) > 600 || ! preg_match('/^[A-Za-z0-9_-]+$/', $encoded)) {
            throw new InvalidArgumentException('Malformed event reference.');
        }
        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);
        $parts = $raw === false ? null : json_decode($raw, true);
        if (! is_array($parts) || count($parts) !== 3 || ! is_string($parts[0]) || ! (is_string($parts[1]) || is_int($parts[1]))
            || ! (is_string($parts[2]) || $parts[2] === null)) {
            throw new InvalidArgumentException('Malformed event reference.');
        }

        return new EventReference($parts[0], (string) $parts[1], $parts[2]);
    }

    public static function tryDecode(string $encoded): ?EventReference
    {
        try {
            return self::decode($encoded);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
