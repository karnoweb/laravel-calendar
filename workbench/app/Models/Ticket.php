<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarModelDefinition;
use Karnoweb\Calendar\Enums\CalendarAction;

class Ticket extends Model
{
    use HasCalendarEvents;

    protected $table = 'workbench_tickets';

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
            sourceKey: 'tickets',
            label: ['fa' => 'تیکت‌ها', 'en' => 'Tickets'],
            color: 'pink',
            startField: 'follow_up_at',
            endField: null,
            allDayField: null,
            defaultDurationMinutes: 30,
            scopeField: 'scope_key',
            ownerField: 'owner_id',
            searchableFields: ['title', 'description'],
            statusOptions: ['open' => 'Open', 'closed' => 'Closed'],
            supportsRecurrence: false,
            authorize: static function (CalendarAction $action, ?self $ticket, CalendarContext $context): bool {
                if ($ticket !== null && $ticket->status === 'closed') {
                    return false;
                }

                return $context->actorId !== null;
            },
        );
    }
}
