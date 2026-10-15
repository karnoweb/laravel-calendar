<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Forms;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Karnoweb\Calendar\Calendar\CalendarSystemRegistry;
use Karnoweb\Calendar\Calendar\IsoDate;
use Karnoweb\Calendar\Calendar\TimezoneConverter;
use Karnoweb\Calendar\Contracts\CalendarFormResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\CalendarFormResult;
use Karnoweb\Calendar\Data\CalendarFormSchema;
use Karnoweb\Calendar\Data\CreateEventInput;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Exceptions\InvalidActionPayload;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;
use Karnoweb\Calendar\Security\CalendarInputNormalizer;

/**
 * Generic create/edit form. Dates are typed in the ACTIVE calendar system (YYYY/MM/DD), times as HH:MM,
 * and everything is converted to instants with the explicit DST policy of TimezoneConverter.
 * Hosts replace this class (CalendarFormResolver binding) for source-specific forms.
 */
final class DefaultCalendarFormResolver implements CalendarFormResolver
{
    public function __construct(
        private readonly CalendarSystemRegistry $systems,
        private readonly TimezoneConverter $converter,
    ) {}

    public function schema(CalendarDefinition $definition, CalendarContext $context, string $sourceKey, ?CalendarEventData $existing = null): CalendarFormSchema
    {
        $fields = [
            ['name' => 'title', 'type' => 'text', 'label' => 'calendar::calendar.form.title', 'required' => true, 'max' => 191],
            ['name' => 'allDay', 'type' => 'checkbox', 'label' => 'calendar::calendar.form.all_day'],
            ['name' => 'startDate', 'type' => 'date', 'label' => 'calendar::calendar.form.start_date', 'required' => true, 'group' => 'when'],
            ['name' => 'startTime', 'type' => 'time', 'label' => 'calendar::calendar.form.start_time', 'group' => 'when'],
            ['name' => 'endDate', 'type' => 'date', 'label' => 'calendar::calendar.form.end_date', 'required' => true, 'group' => 'when'],
            ['name' => 'endTime', 'type' => 'time', 'label' => 'calendar::calendar.form.end_time', 'group' => 'when'],
            ['name' => 'location', 'type' => 'text', 'label' => 'calendar::calendar.form.location', 'max' => 191],
            ['name' => 'description', 'type' => 'textarea', 'label' => 'calendar::calendar.form.description', 'max' => 5000],
        ];

        return new CalendarFormSchema(
            $sourceKey,
            $fields,
            $definition->settings->feature('recurrence'),
            $existing === null ? 'calendar::calendar.form.new_event' : 'calendar::calendar.form.edit_event',
        );
    }

    public function validate(CalendarFormSchema $schema, array $input, CalendarContext $context, CalendarSystem $system): CalendarFormResult
    {
        $impl = $this->systems->get($system);
        $tz = $context->timezone;
        $errors = [];

        $title = FormFieldSanitizer::text($input['title'] ?? '', 191);
        $allDay = FormFieldSanitizer::bool($input['allDay'] ?? false);
        $values = [
            'title' => $title,
            'allDay' => $allDay,
            'startDate' => FormFieldSanitizer::text($input['startDate'] ?? '', 20),
            'startTime' => FormFieldSanitizer::text($input['startTime'] ?? '', 8),
            'endDate' => FormFieldSanitizer::text($input['endDate'] ?? '', 20),
            'endTime' => FormFieldSanitizer::text($input['endTime'] ?? '', 8),
            'startOffset' => FormFieldSanitizer::text($input['startOffset'] ?? '', 8),
            'endOffset' => FormFieldSanitizer::text($input['endOffset'] ?? '', 8),
            'location' => FormFieldSanitizer::text($input['location'] ?? '', 191),
            'description' => FormFieldSanitizer::multiline($input['description'] ?? '', 5000),
        ];
        foreach (['recurrenceFreq', 'recurrenceInterval', 'recurrenceWeekdays', 'recurrenceEnd', 'recurrenceCount', 'recurrenceUntil'] as $key) {
            $values[$key] = $input[$key] ?? null;
        }

        if ($title === '') {
            $errors['title'] = 'calendar::calendar.validation.title_required';
        }

        $start = $end = null;
        try {
            $startIso = $impl->toIso(...CalendarInputNormalizer::parseDate($values['startDate']));
        } catch (InvalidCalendarDate) {
            $startIso = null;
            $errors['startDate'] = 'calendar::calendar.validation.date_invalid';
        }
        try {
            $endIso = $impl->toIso(...CalendarInputNormalizer::parseDate($values['endDate']));
        } catch (InvalidCalendarDate) {
            $endIso = null;
            $errors['endDate'] = 'calendar::calendar.validation.date_invalid';
        }

        if ($startIso !== null && $endIso !== null) {
            if ($allDay) {
                // UI shows the INCLUSIVE last day; the contract stores the EXCLUSIVE end date.
                if ($endIso < $startIso) {
                    $errors['endDate'] = 'calendar::calendar.validation.end_after_start';
                }
                $start = new DateTimeImmutable($startIso.' 00:00:00', new DateTimeZone($tz));
                $end = new DateTimeImmutable(IsoDate::addDays($endIso, 1).' 00:00:00', new DateTimeZone($tz));
            } else {
                $start = $this->instant($startIso, $values['startTime'], $values['startOffset'], $tz, 'startTime', $errors);
                $end = $this->instant($endIso, $values['endTime'], $values['endOffset'], $tz, 'endTime', $errors);
                if ($start !== null && $end !== null && $end <= $start) {
                    $errors['endTime'] = 'calendar::calendar.validation.end_after_start';
                }
            }
        }

        $recurrence = null;
        $freq = FormFieldSanitizer::enum($values['recurrenceFreq'], ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']);
        if ($freq !== '' && $schema->supportsRecurrence && $startIso !== null) {
            $recurrence = $this->recurrence($freq, $values, $impl, $startIso, $system, $tz, $errors);
        }

        if ($errors !== [] || $start === null || $end === null) {
            return new CalendarFormResult(false, $values, $errors);
        }

        try {
            $payloadInput = CreateEventInput::fromPayload([
                'title' => $title,
                'description' => $values['description'],
                'location' => $values['location'],
                'allDay' => $allDay,
                'start' => $allDay ? $start->format('Y-m-d') : $start->setTimezone(new DateTimeZone('UTC'))->format('c'),
                'end' => $allDay ? $end->format('Y-m-d') : $end->setTimezone(new DateTimeZone('UTC'))->format('c'),
                'timezone' => $tz,
                'recurrence' => $recurrence,
            ], $tz);
        } catch (InvalidActionPayload $e) {
            return new CalendarFormResult(false, $values, $e->fieldErrors);
        }

        return new CalendarFormResult(true, $values, [], $payloadInput);
    }

    /** @param array<string, string> $errors */
    private function instant(string $iso, string $time, string $offset, string $tz, string $field, array &$errors): ?DateTimeImmutable
    {
        try {
            [$h, $i] = CalendarInputNormalizer::parseTime($time);

            return $this->converter->localToUtc(sprintf('%s %02d:%02d', $iso, $h, $i), $tz, $offset !== '' ? $offset : null);
        } catch (InvalidCalendarDate $e) {
            $errors[$field] = $e->reason === InvalidCalendarDate::MALFORMED
                ? 'calendar::calendar.validation.time_invalid'
                : FormValidationMapper::dateError($e);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $errors
     * @return array<string, mixed>|null
     */
    private function recurrence(string $freq, array $values, \Karnoweb\Calendar\Calendar\CalendarSystemContract $impl, string $startIso, CalendarSystem $system, string $tz, array &$errors): ?array
    {
        $interval = FormFieldSanitizer::int($values['recurrenceInterval'], 1, 999, 1);
        $weekdays = [];
        if ($freq === 'WEEKLY') {
            foreach ((array) ($values['recurrenceWeekdays'] ?? []) as $d) {
                if (is_numeric($d) && (int) $d >= 0 && (int) $d <= 6) {
                    $weekdays[] = (int) $d;
                }
            }
            if ($weekdays === []) {
                $weekdays = [IsoDate::weekday($startIso)];
            }
        }
        $end = FormFieldSanitizer::enum($values['recurrenceEnd'], ['never', 'count', 'until'], 'never');
        $rule = [
            'frequency' => $freq, 'interval' => $interval, 'byWeekday' => $weekdays,
            'calendarSystem' => $system->value, 'timezone' => $tz,
        ];
        if ($end === 'count') {
            $rule['count'] = FormFieldSanitizer::int($values['recurrenceCount'], 1, 5000, 1);
        } elseif ($end === 'until') {
            try {
                $untilIso = $impl->toIso(...CalendarInputNormalizer::parseDate((string) ($values['recurrenceUntil'] ?? '')));
                if ($untilIso < $startIso) {
                    throw new InvalidArgumentException('until before start');
                }
                $rule['until'] = (new DateTimeImmutable(IsoDate::addDays($untilIso, 1).' 00:00:00', new DateTimeZone($tz)))->modify('-1 second')->setTimezone(new DateTimeZone('UTC'))->format('c');
            } catch (InvalidCalendarDate|InvalidArgumentException) {
                $errors['recurrenceUntil'] = 'calendar::calendar.validation.date_invalid';
            }
        }

        return $rule;
    }
}
