<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Calendar\Contracts\ActionExecutionJournal;
use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Contracts\TimezoneResolver;
use Karnoweb\Calendar\Support\CalendarManager;
use Workbench\App\Calendar\DemoOperationJournal;
use Workbench\App\Calendar\SecureDemoScopeResolver;
use Workbench\App\Calendar\SessionTimezoneResolver;
use Workbench\App\Calendars\ClassesOnlyCalendarFactory;
use Workbench\App\Calendars\ComprehensiveCalendarFactory;
use Workbench\App\Calendars\OperationsCalendarFactory;
use Workbench\App\Calendars\PersonalCalendarFactory;
use Workbench\App\Calendars\PublicCalendarFactory;
use Workbench\App\Calendars\TasksOnlyCalendarFactory;
use Workbench\App\Models\EducationalClass;
use Workbench\App\Models\Meeting;
use Workbench\App\Models\Task;

/** The "host application" side of the integration: everything below belongs to the host, not the package. */
final class WorkbenchCalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ScopeResolver::class, SecureDemoScopeResolver::class);
        $this->app->bind(TimezoneResolver::class, SessionTimezoneResolver::class);
        $this->app->bind(\Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver::class, \Workbench\App\Calendar\DemoGoogleTokenResolver::class);
        $this->app->singleton(ActionExecutionJournal::class, DemoOperationJournal::class);
        $this->app['config']->set('calendar.security.allow_public_calendars', true);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'workbench');
        $this->loadRoutesFrom(dirname(__DIR__, 2).'/routes/web.php');

        $calendars = $this->app->make(CalendarManager::class);
        $calendars->registerModels([
            'tasks' => Task::class,
            'classes' => EducationalClass::class,
            'meetings' => Meeting::class,
        ]);

        $calendars->register('personal', PersonalCalendarFactory::class);
        $calendars->register('operations', OperationsCalendarFactory::class);
        $calendars->register('public', PublicCalendarFactory::class);
        $calendars->register('comprehensive', ComprehensiveCalendarFactory::class);
        $calendars->register('tasks_only', TasksOnlyCalendarFactory::class);
        $calendars->register('classes_only', ClassesOnlyCalendarFactory::class);

        if (! $this->app->runningUnitTests()) {
            try {
                if (! \Illuminate\Support\Facades\Schema::hasTable('workbench_tasks')) {
                    $migration = include dirname(__DIR__, 2).'/database/migrations/2026_01_01_000004_create_workbench_model_tables.php';
                    $migration->up();
                    $this->app->make(\Workbench\Database\Seeders\CalendarDemoSeeder::class)->run();
                } elseif (Task::query()->count() === 0 && EducationalClass::query()->count() === 0 && Meeting::query()->count() === 0) {
                    $this->app->make(\Workbench\Database\Seeders\CalendarDemoSeeder::class)->run();
                }
            } catch (\Throwable) {
                // Ignore if database connection is not yet ready during CLI bootstrap.
            }
        }
    }
}
