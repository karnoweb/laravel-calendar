<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarModelDefinition;

class Task extends Model
{
    use HasCalendarEvents;

    protected $table = 'workbench_tasks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'all_day' => 'boolean',
            'movable' => 'boolean',
            'version' => 'integer',
        ];
    }

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'tasks',
            label: ['fa' => 'وظایف', 'en' => 'Tasks'],
            color: 'blue',
            startField: 'scheduled_for',
            endField: null,
            defaultDurationMinutes: 60,
            scopeField: 'scope_key',
            searchableFields: ['title', 'description', 'location'],
            statusOptions: [
                'open' => 'Open',
                'done' => 'Done',
                'locked' => 'Locked',
            ],
            statusColors: [
                'open' => 'blue',
                'done' => 'green',
                'locked' => 'rose',
            ],
            capabilitiesResolver: static function (self $task, CalendarContext $ctx, ActionCapabilities $base): ActionCapabilities {
                if ($task->status === 'locked' || ! $task->movable) {
                    return new ActionCapabilities(true, false, false, false, false);
                }

                return $base;
            },
        );
    }
}
