<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Calendar\CalendarSystemContract;
use Karnoweb\Calendar\Calendar\DateLabelFormatter;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Security\AllowedCssToken;

/**
 * Turns loaded events into lightweight rows for the client virtual list. Every loaded event is present
 * in the row set (nothing is hidden); the browser only materialises the rows inside the scroll window.
 */
final class ResultWindowManager
{
    public const ROW_HEIGHT = 64;

    public function __construct(private readonly DateLabelFormatter $labels) {}

    /**
     * @param  list<CalendarEventData>  $events  sorted
     * @return list<array<string, mixed>> rows: {t:'day',label} | {t:'event',ref,title,time,color,style,source,readOnly,version}
     */
    public function rows(array $events, CalendarDefinition $definition, CalendarSystemContract $system, string $timezone, string $locale): array
    {
        $rows = [];
        $lastDay = null;
        foreach ($events as $event) {
            $day = $event->firstDate($timezone);
            if ($day !== $lastDay) {
                $rows[] = ['t' => 'day', 'label' => $this->labels->dayTitle($system, $day, $locale), 'iso' => $day];
                $lastDay = $day;
            }
            $source = $definition->source($event->reference->sourceKey);
            $color = AllowedCssToken::color($event->color ?? $source->color);
            $rows[] = [
                't' => 'event',
                'ref' => EventReferenceCodec::encode($event->reference),
                'title' => $event->title,
                'time' => $event->allDay
                    ? trans('calendar::calendar.details.all_day', [], $locale)
                    : $this->labels->time($event->start, $timezone, $locale).' – '.$this->labels->time($event->end, $timezone, $locale),
                'color' => AllowedCssToken::cssClass($color),
                'style' => AllowedCssToken::style($color),
                'source' => CalendarTranslations::label($source->label, $locale),
                'readOnly' => $event->isReadOnly(),
                'version' => $event->version,
            ];
        }

        return $rows;
    }
}
