<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Facades;

use Illuminate\Support\Facades\Facade;
use Karnoweb\Calendar\Support\CalendarManager;

/**
 * @method static void register(string $key, string $factoryClass)
 * @method static \Karnoweb\Calendar\Support\CalendarBuilder make()
 * @method static \Karnoweb\Calendar\Support\CalendarRegistry registry()
 *
 * @see CalendarManager
 */
final class Calendar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CalendarManager::class;
    }
}
