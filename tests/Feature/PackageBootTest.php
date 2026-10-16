<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Karnoweb\Calendar\Livewire\Calendar;
use Karnoweb\Calendar\Tests\FeatureTestCase;
use Karnoweb\Calendar\Tests\Fixtures\FakeStore;
use Livewire\Livewire;

final class PackageBootTest extends FeatureTestCase
{
    public function test_package_registers_no_routes_or_migrations(): void
    {
        $names = array_map(fn ($r) => $r->uri(), Route::getRoutes()->getRoutes());
        foreach ($names as $uri) {
            $this->assertStringNotContainsString('calendar', $uri, 'package must not register calendar routes');
        }
        $this->assertSame([], glob(dirname(__DIR__, 2).'/database/migrations/*') ?: []);
        $this->assertSame([], glob(dirname(__DIR__, 2).'/src/Models/*.php') ?: []);
        foreach (glob(dirname(__DIR__, 2).'/src/*/*.php') ?: [] as $file) {
            $content = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/class\s+\w+\s+extends\s+(Model|\\\\?Illuminate\\\\Database\\\\Eloquent\\\\Model)\b/',
                $content,
                "Package src file [{$file}] must not define an Eloquent Model.",
            );
        }
    }

    public function test_component_renders_month_with_event(): void
    {
        $today = now('Asia/Tehran')->format('Y-m-d');
        FakeStore::add('7', 'Standup meeting', $today.' 10:00:00', $today.' 11:00:00');

        Livewire::test(Calendar::class, ['calendarKey' => 'personal'])
            ->assertSee('Standup meeting')
            ->assertSee('kw-month', false)
            ->assertSet('calendarKey', 'personal');
    }

    public function test_namespaced_component_name_is_registered(): void
    {
        $this->assertNotNull(Livewire::new('calendar::calendar'));
    }
}
