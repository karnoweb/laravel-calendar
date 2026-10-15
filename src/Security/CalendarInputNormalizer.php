<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Security;

use DateTimeImmutable;
use DateTimeZone;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;

/** Normalises user-typed dates/numbers: Persian & Arabic-Indic digits, separators, invalid values. */
final class CalendarInputNormalizer
{
    public static function digits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', '/' => '-',
        ]);
    }

    /** @return array{0:int,1:int,2:int} y, m, d */
    public static function parseDate(string $value): array
    {
        $value = self::digits(trim($value));
        if (! preg_match('/^(\d{3,4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            throw new InvalidCalendarDate('Malformed date.', InvalidCalendarDate::MALFORMED);
        }

        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }

    /** @return array{0:int,1:int} h, i */
    public static function parseTime(string $value): array
    {
        $value = self::digits(trim($value));
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
            throw new InvalidCalendarDate('Malformed time.', InvalidCalendarDate::MALFORMED);
        }

        return [(int) $m[1], (int) $m[2]];
    }

    /** Validates a strict Y-m-d Gregorian date string. */
    public static function gregorianDate(string $value): string
    {
        $value = self::digits(trim($value));
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($dt === false || $dt->format('Y-m-d') !== $value) {
            throw new InvalidCalendarDate('Malformed date.', InvalidCalendarDate::MALFORMED);
        }

        return $value;
    }

    public static function tryGregorianDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            return self::gregorianDate($value);
        } catch (InvalidCalendarDate) {
            return null;
        }
    }
}
