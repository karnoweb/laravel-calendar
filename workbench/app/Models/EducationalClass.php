<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\ActionCapabilities;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarModelDefinition;

class EducationalClass extends Model
{
    use HasCalendarEvents;

    protected $table = 'workbench_educational_classes';

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
            sourceKey: 'classes',
            label: ['fa' => 'کلاس‌های آموزشی', 'en' => 'Classes'],
            color: 'green',
            startField: 'starts_at',
            endField: 'ends_at',
            defaultDurationMinutes: 90,
            scopeField: 'scope_key',
            searchableFields: ['title', 'instructor_name', 'location', 'description'],
            statusOptions: [
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
            statusColors: [
                'scheduled' => 'green',
                'completed' => 'teal',
                'cancelled' => 'rose',
            ],
            categoryOptions: [
                'math' => 'Mathematics',
                'science' => 'Science',
                'language' => 'Language',
            ],
            capabilitiesResolver: static function (self $class, CalendarContext $ctx, ActionCapabilities $base): ActionCapabilities {
                if ($class->status === 'cancelled' || ! $class->movable) {
                    return new ActionCapabilities(true, false, false, false, false);
                }

                return $base;
            },
        );
    }
}
