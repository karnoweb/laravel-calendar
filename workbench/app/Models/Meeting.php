<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\CalendarModelDefinition;

class Meeting extends Model
{
    use HasCalendarEvents;

    protected $table = 'workbench_meetings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'movable' => 'boolean',
            'version' => 'integer',
        ];
    }

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'meetings',
            label: ['fa' => 'جلسات', 'en' => 'Meetings'],
            color: 'purple',
            startField: 'starts_at',
            endField: 'ends_at',
            defaultDurationMinutes: 60,
            scopeField: 'scope_key',
            searchableFields: ['title', 'location', 'description'],
            statusOptions: [
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
            ],
        );
    }
}
