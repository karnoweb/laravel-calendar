<?php

declare(strict_types=1);

namespace Karnoweb\Calendar;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;
use Karnoweb\Calendar\Actions\NullCalendarOperationHooks;
use Karnoweb\Calendar\Cache\CalendarReadCache;
use Karnoweb\Calendar\Cache\NullCalendarCache;
use Karnoweb\Calendar\Calendar\CalendarSystemRegistry;
use Karnoweb\Calendar\Concurrency\NullActionExecutionJournal;
use Karnoweb\Calendar\Contracts\ActionExecutionJournal;
use Karnoweb\Calendar\Contracts\CalendarCache;
use Karnoweb\Calendar\Contracts\CalendarFormResolver;
use Karnoweb\Calendar\Contracts\CalendarObserver;
use Karnoweb\Calendar\Contracts\CalendarOperationHooks;
use Karnoweb\Calendar\Contracts\PreferenceStore;
use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Contracts\TimezoneResolver;
use Karnoweb\Calendar\Forms\DefaultCalendarFormResolver;
use Karnoweb\Calendar\Observability\CalendarTelemetry;
use Karnoweb\Calendar\Observability\NullCalendarTelemetry;
use Karnoweb\Calendar\Providers\AppTimezoneResolver;
use Karnoweb\Calendar\Providers\DenyAnonymousScopeResolver;
use Karnoweb\Calendar\Providers\NullCalendarObserver;
use Karnoweb\Calendar\Providers\SessionPreferenceStore;
use Karnoweb\Calendar\Support\CalendarManager;
use Karnoweb\Calendar\Support\CalendarModelRegistry;
use Karnoweb\Calendar\Support\CalendarRegistry;
use Livewire\Livewire;

final class CalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/calendar.php', 'calendar');

        // Registry/manager hold only the allowlist of factory/model CLASS names, never user state.
        $this->app->singleton(CalendarRegistry::class);
        $this->app->singleton(CalendarModelRegistry::class);
        $this->app->singleton(CalendarManager::class);
        $this->app->singleton(CalendarSystemRegistry::class);

        // Fail-closed defaults; hosts rebind these.
        $this->app->bind(ScopeResolver::class, DenyAnonymousScopeResolver::class);
        $this->app->bind(TimezoneResolver::class, AppTimezoneResolver::class);
        $this->app->bindIf(\Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver::class, \Karnoweb\Calendar\Google\NullGoogleAccessTokenResolver::class);
        $this->app->bind(CalendarOperationHooks::class, NullCalendarOperationHooks::class);
        $this->app->bind(ActionExecutionJournal::class, NullActionExecutionJournal::class);
        $this->app->bind(PreferenceStore::class, SessionPreferenceStore::class);
        $this->app->bind(CalendarFormResolver::class, DefaultCalendarFormResolver::class);

        $this->app->bind(CalendarObserver::class, function ($app) {
            $class = config('calendar.observability.observer');

            return is_string($class) && is_subclass_of($class, CalendarObserver::class)
                ? $app->make($class)
                : new NullCalendarObserver;
        });
        $this->app->bind(CalendarTelemetry::class, function ($app) {
            return config('calendar.observability.observer')
                ? new CalendarTelemetry($app->make(CalendarObserver::class))
                : new NullCalendarTelemetry;
        });

        $this->app->bind(CalendarCache::class, function ($app) {
            if (! config('calendar.cache.enabled', false)) {
                return new NullCalendarCache;
            }

            return new CalendarReadCache($app->make(CacheFactory::class)->store(config('calendar.cache.store')));
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'calendar');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'calendar');

        Livewire::addNamespace(
            namespace: 'calendar',
            classNamespace: 'Karnoweb\\Calendar\\Livewire',
            classPath: __DIR__.'/Livewire',
            classViewPath: __DIR__.'/../resources/views/livewire',
        );

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/calendar.php' => config_path('calendar.php')], 'calendar-config');
            $this->publishes([__DIR__.'/../public/dist' => public_path('vendor/calendar')], 'calendar-assets');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/calendar')], 'calendar-views');
            $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/calendar')], 'calendar-lang');
        }
    }
}
