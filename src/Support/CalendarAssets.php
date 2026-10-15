<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

/** Reads the prebuilt, self-contained CSS/JS bundles shipped with the package (no host build step needed). */
final class CalendarAssets
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function css(): string
    {
        return self::read('calendar.css');
    }

    public static function js(): string
    {
        return self::read('calendar.js');
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function read(string $file): string
    {
        if (isset(self::$cache[$file])) {
            return self::$cache[$file];
        }
        $path = dirname(__DIR__, 2).'/public/dist/'.$file;
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        // The bundle is embedded in <style>/<script>; a closing tag inside would break out of it.
        return self::$cache[$file] = str_ireplace(['</style', '</script'], ['<\/style', '<\/script'], $contents);
    }
}
