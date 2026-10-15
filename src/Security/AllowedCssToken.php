<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Security;

/**
 * Whitelist for colour/class values coming from providers or settings.
 * Only named tokens or strict #rgb/#rrggbb values are accepted; anything else is rejected.
 */
final class AllowedCssToken
{
    public const NAMED = ['gray', 'red', 'orange', 'amber', 'green', 'teal', 'blue', 'indigo', 'purple', 'pink'];

    public const THEMES = ['light', 'dark', 'auto', 'luxe'];

    public const DIRECTIONS = ['rtl', 'ltr', 'auto'];

    public static function isValidColor(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array($value, self::NAMED, true) || (bool) preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value);
    }

    /** Returns a safe token or the fallback; never an arbitrary string. */
    public static function color(?string $value, string $fallback = 'blue'): string
    {
        return self::isValidColor($value) ? strtolower((string) $value) : $fallback;
    }

    public static function isHex(string $color): bool
    {
        return $color !== '' && $color[0] === '#';
    }

    /** CSS class for a named colour; hex colours use the inline custom property from {@see style()}. */
    public static function cssClass(string $color): string
    {
        $color = self::color($color);

        return self::isHex($color) ? 'kw-calendar-color-custom' : 'kw-calendar-color-'.$color;
    }

    public static function style(string $color): string
    {
        $color = self::color($color);

        return self::isHex($color) ? '--kw-event-color:'.$color : '';
    }

    public static function theme(string $value): string
    {
        return in_array($value, self::THEMES, true) ? $value : 'auto';
    }

    public static function direction(string $value): string
    {
        return in_array($value, self::DIRECTIONS, true) ? $value : 'auto';
    }
}
