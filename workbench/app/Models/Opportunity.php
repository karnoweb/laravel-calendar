<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\CalendarModelDefinition;

class Opportunity extends Model
{
    use HasCalendarEvents;

    protected $table = 'workbench_opportunities';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'follow_up_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'opportunities',
            label: ['fa' => 'فرصت‌های فروش', 'en' => 'Opportunities'],
            color: 'purple',
            startField: 'follow_up_at',
            endField: null,
            allDayField: null,
            defaultDurationMinutes: 60,
            scopeField: 'scope_key',
            searchableFields: ['title', 'stage'],
            statusOptions: ['open' => 'Open', 'won' => 'Won'],
            supportsRecurrence: false,
            displayFields: [
                ['field' => 'stage', 'label' => ['fa' => 'مرحله', 'en' => 'Stage']],
            ],
        );
    }
}
