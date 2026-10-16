<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

/** Locale helpers: text direction and a safe label fallback chain. */
final class CalendarTranslations
{
    private const RTL = ['fa', 'ar', 'he', 'ur', 'ps', 'ku'];

    public static function isRtl(string $locale): bool
    {
        return in_array(strtolower(substr($locale, 0, 2)), self::RTL, true);
    }

    /** Resolve "rtl"/"ltr" from a style setting ('auto' follows the locale). */
    public static function direction(string $setting, string $locale): string
    {
        return match ($setting) {
            'rtl', 'ltr' => $setting,
            default => self::isRtl($locale) ? 'rtl' : 'ltr',
        };
    }

    private const FA_LABELS = [
        'Official holidays' => 'تعطیلات رسمی',
        'Tasks' => 'وظایف',
        'Classes' => 'کلاس‌های آموزشی',
        'Educational Classes' => 'کلاس‌های آموزشی',
        'Meetings' => 'جلسات',
        'Shifts' => 'شیفت‌ها',
        'Reservations' => 'رزروها',
        'Status' => 'وضعیت',
        'Category' => 'دسته‌بندی',
        'Open' => 'در انتظار انجام',
        'Done' => 'تکمیل شده',
        'Locked' => 'قفل شده',
        'Scheduled' => 'برنامه‌ریزی شده',
        'Completed' => 'برگزار شده',
        'Cancelled' => 'لغو شده',
        'Mathematics' => 'ریاضیات',
        'Science' => 'علوم پایه',
        'Language' => 'زبان',
    ];

    /** Translate a label key (or return plain text unchanged), falling back to English then the key. */
    public static function label(string $labelOrKey, string $locale): string
    {
        if (! str_contains($labelOrKey, '::') && ! str_contains($labelOrKey, '.')) {
            if (str_starts_with(strtolower($locale), 'fa') && isset(self::FA_LABELS[$labelOrKey])) {
                return self::FA_LABELS[$labelOrKey];
            }

            return $labelOrKey;
        }
        $t = trans($labelOrKey, [], $locale);
        if ($t === $labelOrKey) {
            $t = trans($labelOrKey, [], 'en');
        }

        return is_string($t) ? $t : $labelOrKey;
    }
}
