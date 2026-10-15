<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Calendar\Contracts\ActionExecutionJournal;
use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Contracts\TimezoneResolver;
use Karnoweb\Calendar\Support\CalendarManager;
use Workbench\App\Calendar\SessionTimezoneResolver;
use Workbench\App\Calendar\DemoOperationJournal;
use Workbench\App\Calendar\SecureDemoScopeResolver;
use Workbench\App\Calendars\OperationsCalendarFactory;
use Workbench\App\Calendars\PersonalCalendarFactory;
use Workbench\App\Calendars\PublicCalendarFactory;

/** The "host application" side of the integration: everything below belongs to the host, not the package. */
final class WorkbenchCalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ScopeResolver::class, SecureDemoScopeResolver::class);
        $this->app->bind(TimezoneResolver::class, SessionTimezoneResolver::class);
        $this->app->bind(\Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver::class, \Workbench\App\Calendar\DemoGoogleTokenResolver::class);        $this->app->singleton(ActionExecutionJournal::class, DemoOperationJournal::class);
        $this->app['config']->set('calendar.security.allow_public_calendars', true);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'workbench');
        $this->loadRoutesFrom(dirname(__DIR__, 2).'/routes/web.php');

        $calendars = $this->app->make(CalendarManager::class);
        $calendars->register('personal', PersonalCalendarFactory::class);
        $calendars->register('operations', OperationsCalendarFactory::class);
        $calendars->register('public', PublicCalendarFactory::class);
    }
}
