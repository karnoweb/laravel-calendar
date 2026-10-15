<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Forms;

use Karnoweb\Calendar\Security\SafeCalendarMarkup;

/** Sanitises every form value by field type; no raw HTML ever survives. */
final class FormFieldSanitizer
{
    public static function text(mixed $value, int $max = 191): string
    {
        return is_scalar($value) ? SafeCalendarMarkup::text((string) $value, $max) : '';
    }

    public static function multiline(mixed $value, int $max = 5000): string
    {
        return is_scalar($value) ? SafeCalendarMarkup::multiline((string) $value, $max) : '';
    }

    public static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @param list<string> $allowed */
    public static function enum(mixed $value, array $allowed, string $default = ''): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    public static function url(mixed $value): ?string
    {
        return is_string($value) ? SafeCalendarMarkup::url($value) : null;
    }

    public static function int(mixed $value, int $min, int $max, int $default): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d{1,6}$/', \Karnoweb\Calendar\Security\CalendarInputNormalizer::digits((string) $value))) {
            return $default;
        }

        return max($min, min($max, (int) \Karnoweb\Calendar\Security\CalendarInputNormalizer::digits((string) $value)));
    }
}
