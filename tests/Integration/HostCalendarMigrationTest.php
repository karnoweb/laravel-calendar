<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use Illuminate\Support\Facades\Schema;
use Karnoweb\Calendar\Tests\WorkbenchTestCase;

final class HostCalendarMigrationTest extends WorkbenchTestCase
{
    public function test_host_owns_its_domain_tables(): void
    {
        $this->assertTrue(Schema::hasTable('demo_tasks'));
        $this->assertTrue(Schema::hasTable('demo_recurring_series'));
        $this->assertTrue(Schema::hasTable('demo_action_journal'));
        $this->assertTrue(Schema::hasColumns('demo_tasks', ['scope_key', 'starts_at', 'ends_at', 'version']));
    }

    public function test_package_creates_no_tables_of_its_own(): void
    {
        $tables = array_map(fn ($t) => is_array($t) ? $t['name'] : (string) $t, Schema::getTables());
        foreach ($tables as $name) {
            $this->assertStringStartsNotWith('calendar', $name, "package must not own table [{$name}]");
            $this->assertStringStartsNotWith('kw_calendar', $name);
        }
    }

    public function test_package_source_contains_no_schema_or_model_code(): void
    {
        $src = dirname(__DIR__, 2).'/src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            $this->assertStringNotContainsString('Schema::', $code, $file->getFilename());
            $this->assertStringNotContainsString('extends Model', $code, $file->getFilename());
            $this->assertStringNotContainsString('Route::', $code, $file->getFilename());
            $this->assertStringNotContainsString('Illuminate\\Database\\Migrations', $code, $file->getFilename());
        }
        $this->assertDirectoryDoesNotExist(dirname(__DIR__, 2).'/database');
    }
}
