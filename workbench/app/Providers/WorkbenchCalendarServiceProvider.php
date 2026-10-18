<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Karnoweb\Calendar\Contracts\ActionExecutionJournal;
use Karnoweb\Calendar\Contracts\GoogleAccessTokenResolver;
use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Contracts\TimezoneResolver;
use Karnoweb\Calendar\Support\CalendarManager;
use Karnoweb\Calendar\Support\EventDetailsRegistry;
use Workbench\App\Calendar\DemoGoogleTokenResolver;
use Workbench\App\Calendar\DemoOperationJournal;
use Workbench\App\Calendar\OrderDetailsSection;
use Workbench\App\Calendar\SecureDemoScopeResolver;
use Workbench\App\Calendar\SessionTimezoneResolver;
use Workbench\App\Calendars\ClassesOnlyCalendarFactory;
use Workbench\App\Calendars\ComprehensiveCalendarFactory;
use Workbench\App\Calendars\EnterpriseSampleCalendarFactory;
use Workbench\App\Calendars\OperationsCalendarFactory;
use Workbench\App\Calendars\PersonalCalendarFactory;
use Workbench\App\Calendars\PublicCalendarFactory;
use Workbench\App\Calendars\TasksOnlyCalendarFactory;
use Workbench\App\Models\EducationalClass;
use Workbench\App\Models\Meeting;
use Workbench\App\Models\Opportunity;
use Workbench\App\Models\Order;
use Workbench\App\Models\Task;
use Workbench\App\Models\Ticket;
use Workbench\Database\Seeders\CalendarDemoSeeder;

/** The "host application" side of the integration: everything below belongs to the host, not the package. */
final class WorkbenchCalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ScopeResolver::class, SecureDemoScopeResolver::class);
        $this->app->bind(TimezoneResolver::class, SessionTimezoneResolver::class);
        $this->app->bind(GoogleAccessTokenResolver::class, DemoGoogleTokenResolver::class);
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
            'orders' => Order::class,
            'tickets' => Ticket::class,
            'opportunities' => Opportunity::class,
        ]);
        $this->app->make(EventDetailsRegistry::class)->register('orders', 'fulfillment', 'Order fulfillment', OrderDetailsSection::class);

        $calendars->register('personal', PersonalCalendarFactory::class);
        $calendars->register('operations', OperationsCalendarFactory::class);
        $calendars->register('public', PublicCalendarFactory::class);
        $calendars->register('comprehensive', ComprehensiveCalendarFactory::class);
        $calendars->register('tasks_only', TasksOnlyCalendarFactory::class);
        $calendars->register('classes_only', ClassesOnlyCalendarFactory::class);
        foreach (['my.calendar', 'education.calendar', 'crm.calendar', 'delivery.calendar', 'overview.calendar'] as $key) {
            $calendars->register($key, EnterpriseSampleCalendarFactory::class);
        }

        if (! $this->app->runningUnitTests()) {
            try {
                if (! Schema::hasTable('workbench_tasks')) {
                    $migration = include dirname(__DIR__, 2).'/database/migrations/2026_01_01_000004_create_workbench_model_tables.php';
                    $migration->up();
                    $this->app->make(CalendarDemoSeeder::class)->run();
                } elseif (Task::query()->count() === 0 && EducationalClass::query()->count() === 0 && Meeting::query()->count() === 0) {
                    $this->app->make(CalendarDemoSeeder::class)->run();
                }
            } catch (\Throwable) {
                // Ignore if database connection is not yet ready during CLI bootstrap.
            }
        }
    }
}
