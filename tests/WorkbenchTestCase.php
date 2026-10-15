<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Workbench\App\Providers\WorkbenchCalendarServiceProvider;

/** Boots the package together with the demo host application (workbench): real DB, real handler, real policy. */
abstract class WorkbenchTestCase extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), WorkbenchCalendarServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('en');
    }

    protected function loginAs(string $actor, string $tenant, ?string $tz = null): void
    {
        session()->put(['demo_actor' => $actor, 'demo_tenant' => $tenant]);
        if ($tz !== null) {
            session()->put('demo_timezone', $tz);
        }
    }

    protected function logout(): void
    {
        session()->flush();
    }
}
