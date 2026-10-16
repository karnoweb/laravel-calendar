<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Security;

/**
 * Event text is always rendered escaped. This helper additionally strips control and
 * bidi-override characters and restricts URLs. It NEVER produces HTML.
 */
final class SafeCalendarMarkup
{
    /** Remove control chars and bidi overrides (U+202A-U+202E, U+2066-U+2069), collapse whitespace. */
    public static function text(?string $value, int $maxLength = 500): string
    {
        $value ??= '';
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = (string) preg_replace('/[\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value);
        $value = trim($value);

        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
    }

    /** Multi-line plain text (keeps \n). */
    public static function multiline(?string $value, int $maxLength = 5000): string
    {
        $value ??= '';
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = (string) preg_replace('/[\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value);
        $value = trim($value);

        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
    }

    /** Allow only http(s), mailto, tel and same-site absolute paths. Returns null otherwise. */
    public static function url(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F\s]/', $url) || strlen($url) > 2048) {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\')) {
            return $url;
        }
        if (preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
            return $url;
        }

        return null;
    }
}
