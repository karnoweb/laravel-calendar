<?php

declare(strict_types=1);

return [
    /*
    | Fallback timezone when the host timezone resolver does not decide otherwise.
    | The browser can never choose a timezone.
    */
    'timezone' => env('CALENDAR_TIMEZONE', config('app.timezone', 'UTC')),

    'default_view' => 'month',

    /*
    | 'auto' => fa locale uses Jalali, every other locale Gregorian. Per-calendar CalendarSettings override this.
    */
    'default_calendar_system' => 'auto',

    // 0 = Sunday ... 6 = Saturday
    'first_weekday_gregorian' => 0,
    'first_weekday_jalali' => 6,
    'weekend_days_gregorian' => [0, 6],
    'weekend_days_jalali' => [5],

    'limits' => [
        'max_range_days' => 366,
        'max_events_per_response' => 250,
        'max_occurrences' => 500,
        'max_search_length' => 120,
    ],

    'security' => [
        // Anonymous read is only possible when BOTH this is true AND the calendar's
        // CalendarSettings::allowAnonymousRead is true. Writes are never allowed for guests.
        'allow_public_calendars' => true,
    ],

    'rate_limit' => [
        'enabled' => true,
        'read_per_minute' => 240,
        'write_per_minute' => 60,
    ],

    /*
    | Provider page cache. OFF by default: enabling it is a deliberate decision because cached
    | pages are keyed per calendar+scope+actor+source+query but live until TTL or invalidation.
    */
    'cache' => [
        'enabled' => false,
        'store' => null,
        'ttl' => 60,
    ],

    'observability' => [
        // Set to \Karnoweb\Calendar\Providers\LogCalendarObserver::class to log scalar-only observations.
        'observer' => null,
        'log_channel' => null,
    ],

    'refresh' => [
        // wire:poll interval in seconds (0 = off). Realtime is optional and never required.
        'poll_seconds' => 0,
        'broadcast' => [
            'enabled' => false,
        ],
    ],

    'preferences' => [
        'enabled' => false,
    ],

    /*
    | Google Calendar source (optional). The host owns OAuth and binds
    | Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver. Scope needed:
    |   read-only : https://www.googleapis.com/auth/calendar.readonly
    |   writable  : https://www.googleapis.com/auth/calendar.events
    */
    'google' => [
        'calendar_id' => env('CALENDAR_GOOGLE_CALENDAR_ID', 'primary'),
        'writable' => (bool) env('CALENDAR_GOOGLE_WRITABLE', false),
        'api_base' => 'https://www.googleapis.com/calendar/v3',
        'timeout' => 8,
    ],

    /*
    | Official holidays source (enable per calendar with ->holidays()).
    | jalali/gregorian: fixed yearly holidays as [month, day, 'English', 'فارسی'].
    | dates: one-off dates (lunar holidays move yearly; list them here per year).
    */
    'holidays' => [
        'jalali' => [
            [1, 1, 'Nowruz', 'نوروز'],
            [1, 2, 'Nowruz', 'نوروز'],
            [1, 3, 'Nowruz', 'نوروز'],
            [1, 4, 'Nowruz', 'نوروز'],
            [1, 12, 'Islamic Republic Day', 'روز جمهوری اسلامی'],
            [1, 13, 'Nature Day (Sizdah Bedar)', 'روز طبیعت'],
            [3, 14, 'Demise of Imam Khomeini', 'رحلت امام خمینی'],
            [3, 15, '15 Khordad Uprising', 'قیام ۱۵ خرداد'],
            [11, 22, 'Victory of the Islamic Revolution', 'پیروزی انقلاب اسلامی'],
            [12, 29, 'Oil Nationalization Day', 'ملی شدن صنعت نفت'],
        ],
        'gregorian' => [],
        'dates' => [],
    ],

    'assets' => [
        // inline: CSS/JS are injected once through Livewire @assets (no publish needed).
        // published: expects php artisan vendor:publish --tag=calendar-assets and tags in the host layout.
        'mode' => 'inline',
        'path' => 'vendor/calendar',
    ],
];
