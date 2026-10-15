<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests;

use Karnoweb\Calendar\Contracts\ScopeResolver;
use Karnoweb\Calendar\Support\CalendarManager;
use Karnoweb\Calendar\Tests\Fixtures\FakeCalendarFactory;
use Karnoweb\Calendar\Tests\Fixtures\FakeScopeResolver;
use Karnoweb\Calendar\Tests\Fixtures\FakeStore;

abstract class FeatureTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeStore::reset();
        $this->app->singleton(ScopeResolver::class, FakeScopeResolver::class);
        $this->app->make(CalendarManager::class)->register('personal', FakeCalendarFactory::class);
        $this->app->setLocale('en');
    }
}
