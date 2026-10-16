<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Concerns;

use Illuminate\Support\Str;
use Karnoweb\Calendar\Data\CalendarModelDefinition;

/**
 * Optional Eloquent adapter trait. Adding this trait to a model does NOT automatically expose it;
 * the host must also register the model in CalendarModelRegistry (via Calendar::registerModel()).
 *
 * Models customize their calendar behavior by overriding `calendarDefinition()`.
 */
trait HasCalendarEvents
{
    public static function calendarDefinition(): CalendarModelDefinition
    {
        $base = class_basename(static::class);
        $plural = Str::pluralStudly($base);
        $sourceKey = Str::snake($plural);
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $sourceKey)) {
            $sourceKey = 'events';
        }

        return new CalendarModelDefinition(
            sourceKey: $sourceKey,
            label: Str::headline($plural),
        );
    }
}
