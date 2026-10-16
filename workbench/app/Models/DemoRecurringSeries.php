<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Karnoweb\Calendar\Data\RecurrenceExceptionData;
use Karnoweb\Calendar\Data\RecurrenceRuleData;
use Karnoweb\Calendar\Data\RecurrenceSeriesData;

class DemoRecurringSeries extends Model
{
    protected $table = 'demo_recurring_series';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'all_day' => 'boolean', 'movable' => 'boolean', 'rule' => 'array', 'exceptions' => 'array', 'version' => 'integer'];
    }

    public function toSeriesData(): RecurrenceSeriesData
    {
        $tz = new DateTimeZone($this->timezone);
        $exceptions = array_map(
            fn (array $e) => new RecurrenceExceptionData($e['key'], $e['kind'], $e['replacement'] ?? null),
            $this->exceptions ?? [],
        );

        return new RecurrenceSeriesData(
            (string) $this->id,
            $this->timezone,
            DateTimeImmutable::createFromInterface($this->starts_at)->setTimezone($tz),
            DateTimeImmutable::createFromInterface($this->ends_at)->setTimezone($tz),
            RecurrenceRuleData::fromArray($this->rule, $this->timezone),
            $exceptions,
            (string) $this->version,
            (bool) $this->all_day,
        );
    }

    /** @param list<RecurrenceExceptionData> $exceptions */
    public static function exceptionsToArray(array $exceptions): array
    {
        return array_map(fn (RecurrenceExceptionData $e) => ['key' => $e->originalOccurrenceKey, 'kind' => $e->kind, 'replacement' => $e->replacement], $exceptions);
    }
}
