<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\CalendarModelDefinition;
use Workbench\App\Calendar\DemoTaskHandler;
use Workbench\App\Calendar\DemoTaskProvider;
use Workbench\Database\Factories\DemoTaskFactory;

class DemoTask extends Model
{
    use HasCalendarEvents;
    use HasFactory;

    protected $table = 'demo_tasks';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'all_day' => 'boolean', 'movable' => 'boolean', 'version' => 'integer'];
    }

    protected static function newFactory(): DemoTaskFactory
    {
        return DemoTaskFactory::new();
    }

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'demo_tasks',
            label: ['fa' => 'وظایف دمو', 'en' => 'Demo Tasks'],
            color: 'blue',
            startField: 'starts_at',
            endField: 'ends_at',
            scopeField: 'scope_key',
            statusOptions: ['open' => 'Open', 'done' => 'Done', 'locked' => 'Locked'],
            providerClass: DemoTaskProvider::class,
            handlerClass: DemoTaskHandler::class,
        );
    }
}
