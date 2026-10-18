<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\CalendarModelDefinition;

class Order extends Model
{
    use HasCalendarEvents;

    protected $table = 'workbench_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'delivery_at' => 'datetime',
            'all_day' => 'boolean',
            'version' => 'integer',
        ];
    }

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'orders',
            label: ['fa' => 'سفارش‌ها', 'en' => 'Orders'],
            color: 'amber',
            startField: 'delivery_at',
            endField: null,
            defaultDurationMinutes: 1440,
            defaultAllDay: true,
            scopeField: 'scope_key',
            searchableFields: ['title', 'tracking_code'],
            statusOptions: ['scheduled' => 'Scheduled', 'delivered' => 'Delivered'],
            readOnly: true,
            supportsRecurrence: false,
            displayFields: [
                ['field' => 'tracking_code', 'label' => ['fa' => 'رهگیری', 'en' => 'Tracking']],
            ],
        );
    }
}
