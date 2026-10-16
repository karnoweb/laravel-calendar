<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Forms;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Karnoweb\Calendar\Calendar\CalendarSystemContract;
use Karnoweb\Calendar\Calendar\CalendarSystemRegistry;
use Karnoweb\Calendar\Calendar\IsoDate;
use Karnoweb\Calendar\Calendar\TimezoneConverter;
use Karnoweb\Calendar\Contracts\CalendarFormResolver;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\CalendarFormResult;
use Karnoweb\Calendar\Data\CalendarFormSchema;
use Karnoweb\Calendar\Data\CalendarModelDefinition;
use Karnoweb\Calendar\Data\CreateEventInput;
use Karnoweb\Calendar\Enums\CalendarSystem;
use Karnoweb\Calendar\Exceptions\InvalidActionPayload;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDate;
use Karnoweb\Calendar\Security\CalendarInputNormalizer;
use Karnoweb\Calendar\Support\CalendarModelRegistry;

/**
 * Generic create/edit form. Dates are typed in the ACTIVE calendar system (YYYY/MM/DD), times as HH:MM,
 * and everything is converted to instants with the explicit DST policy of TimezoneConverter.
 * Automatically integrates custom formFields, statusOptions, categoryOptions, and recurrence rules
 * from registered Eloquent models (HasCalendarEvents).
 */
final class DefaultCalendarFormResolver implements CalendarFormResolver
{
    private CalendarModelRegistry $models;

    public function __construct(
        private readonly CalendarSystemRegistry $systems,
        private readonly TimezoneConverter $converter,
        ?CalendarModelRegistry $models = null,
    ) {
        $this->models = $models ?? (app()->bound(CalendarModelRegistry::class)
            ? app(CalendarModelRegistry::class)
            : new CalendarModelRegistry);
    }

    public function schema(CalendarDefinition $definition, CalendarContext $context, string $sourceKey, ?CalendarEventData $existing = null): CalendarFormSchema
    {
        $modelDef = $this->resolveModelDefinition($definition, $sourceKey);
        $titleKey = $existing === null ? 'calendar::calendar.form.new_event' : 'calendar::calendar.form.edit_event';

        if ($modelDef !== null && $modelDef->formFields !== null) {
            return new CalendarFormSchema(
                $sourceKey,
                $modelDef->formFields,
                $modelDef->supportsRecurrence && $definition->settings->feature('recurrence'),
                $titleKey,
            );
        }

        $fields = [
            ['name' => 'title', 'type' => 'text', 'label' => 'calendar::calendar.form.title', 'required' => true, 'max' => 191],
            ['name' => 'allDay', 'type' => 'checkbox', 'label' => 'calendar::calendar.form.all_day', 'default' => $modelDef?->defaultAllDay ?? false],
            ['name' => 'startDate', 'type' => 'date', 'label' => 'calendar::calendar.form.start_date', 'required' => true, 'group' => 'when'],
            ['name' => 'startTime', 'type' => 'time', 'label' => 'calendar::calendar.form.start_time', 'group' => 'when'],
            ['name' => 'endDate', 'type' => 'date', 'label' => 'calendar::calendar.form.end_date', 'required' => $modelDef === null || $modelDef->endField !== null, 'group' => 'when'],
            ['name' => 'endTime', 'type' => 'time', 'label' => 'calendar::calendar.form.end_time', 'group' => 'when'],
        ];

        if ($modelDef !== null && $modelDef->statusField !== null && $modelDef->statusOptions !== []) {
            $fields[] = [
                'name' => 'status',
                'type' => 'select',
                'label' => is_string($modelDef->statusFilterLabel) ? $modelDef->statusFilterLabel : 'Status',
                'options' => $modelDef->statusOptions,
                'default' => $modelDef->defaultStatus() ?? '',
            ];
        }

        if ($modelDef !== null && $modelDef->categoryField !== null && $modelDef->categoryOptions !== []) {
            $fields[] = [
                'name' => 'category',
                'type' => 'select',
                'label' => is_string($modelDef->categoryFilterLabel) ? $modelDef->categoryFilterLabel : 'Category',
                'options' => $modelDef->categoryOptions,
                'default' => $modelDef->defaultCategory() ?? '',
            ];
        }

        $fields[] = ['name' => 'location', 'type' => 'text', 'label' => 'calendar::calendar.form.location', 'max' => 191];
        $fields[] = ['name' => 'description', 'type' => 'textarea', 'label' => 'calendar::calendar.form.description', 'max' => 5000];

        $supportsRecurrence = $modelDef !== null
            ? ($modelDef->supportsRecurrence && $definition->settings->feature('recurrence'))
            : $definition->settings->feature('recurrence');

        return new CalendarFormSchema(
            $sourceKey,
            $fields,
            $supportsRecurrence,
            $titleKey,
        );
    }

    public function validate(CalendarFormSchema $schema, array $input, CalendarContext $context, CalendarSystem $system): CalendarFormResult
    {
        $modelDef = $this->models->has($schema->sourceKey) ? $this->models->definition($schema->sourceKey) : null;
        $impl = $this->systems->get($system);
        $tz = $context->timezone;
        $errors = [];

        $title = FormFieldSanitizer::text($input['title'] ?? '', 191);
        $allDay = FormFieldSanitizer::bool($input['allDay'] ?? false);
        $movable = array_key_exists('movable', $input) ? FormFieldSanitizer::bool($input['movable']) : true;
        $values = [
            'title' => $title,
            'allDay' => $allDay,
            'movable' => $movable,
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

        $endDateOmitted = $values['endDate'] === '' && $modelDef !== null && $modelDef->endField === null;
        if ($endDateOmitted) {
            $endIso = $startIso;
        } else {
            try {
                $endIso = $impl->toIso(...CalendarInputNormalizer::parseDate($values['endDate']));
            } catch (InvalidCalendarDate) {
                $endIso = null;
                $errors['endDate'] = 'calendar::calendar.validation.date_invalid';
            }
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
                if ($endDateOmitted && $values['endTime'] === '' && $modelDef !== null && $start !== null) {
                    $end = $start->modify("+{$modelDef->defaultDurationMinutes} minutes");
                } else {
                    $end = $this->instant($endIso, $values['endTime'], $values['endOffset'], $tz, 'endTime', $errors);
                }
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

        [$extra, $customErrors] = $this->validateCustomFields($schema, $input, $values);
        $errors += $customErrors;

        if ($errors !== [] || $start === null || $end === null) {
            return new CalendarFormResult(false, $values, $errors);
        }

        $status = isset($extra['status']) && is_string($extra['status']) && $extra['status'] !== '' ? $extra['status'] : null;
        $category = isset($extra['category']) && is_string($extra['category']) && $extra['category'] !== '' ? $extra['category'] : null;

        try {
            $payloadInput = CreateEventInput::fromPayload([
                'title' => $title,
                'description' => $values['description'],
                'location' => $values['location'],
                'status' => $status,
                'category' => $category,
                'allDay' => $allDay,
                'movable' => $movable,
                'start' => $allDay ? $start->format('Y-m-d') : $start->setTimezone(new DateTimeZone('UTC'))->format('c'),
                'end' => $allDay ? $end->format('Y-m-d') : $end->setTimezone(new DateTimeZone('UTC'))->format('c'),
                'timezone' => $tz,
                'recurrence' => $recurrence,
                'extra' => $extra,
            ], $tz);
        } catch (InvalidActionPayload $e) {
            return new CalendarFormResult(false, $values, $e->fieldErrors);
        }

        return new CalendarFormResult(true, $values, [], $payloadInput);
    }

    /**
     * Validate schema-declared custom fields and discard any unallowed input keys.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $values
     * @return array{0: array<string, scalar|null>, 1: array<string, string>}
     */
    private function validateCustomFields(CalendarFormSchema $schema, array $input, array &$values): array
    {
        $standard = ['title', 'allDay', 'startDate', 'startTime', 'endDate', 'endTime', 'location', 'description', 'movable'];
        $extra = [];
        $errors = [];

        foreach ($schema->fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || in_array($name, $standard, true)) {
                continue;
            }
            $type = (string) ($field['type'] ?? 'text');
            $required = ! empty($field['required']);
            $raw = $input[$name] ?? ($field['default'] ?? null);

            if ($type === 'checkbox') {
                $boolVal = FormFieldSanitizer::bool($raw ?? false);
                $values[$name] = $boolVal;
                $extra[$name] = $boolVal;

                continue;
            }

            $max = isset($field['max']) && is_int($field['max']) ? $field['max'] : 500;
            $strVal = $type === 'textarea'
                ? FormFieldSanitizer::multiline($raw ?? '', $max)
                : FormFieldSanitizer::text($raw ?? '', $max);
            $values[$name] = $strVal;

            if ($strVal === '') {
                if ($required) {
                    $errors[$name] = 'calendar::calendar.validation.title_required';
                }

                continue;
            }

            if ($type === 'select' && isset($field['options']) && is_array($field['options'])) {
                $allowedKeys = array_map('strval', array_keys($field['options']));
                if (! in_array($strVal, $allowedKeys, true)) {
                    $errors[$name] = 'calendar::calendar.validation.date_invalid';

                    continue;
                }
            } elseif ($type === 'number') {
                if (! is_numeric($strVal)) {
                    $errors[$name] = 'calendar::calendar.validation.date_invalid';

                    continue;
                }
                $numVal = str_contains($strVal, '.') ? (float) $strVal : (int) $strVal;
                $values[$name] = $numVal;
                $extra[$name] = $numVal;

                continue;
            }

            $extra[$name] = $strVal;
        }

        return [$extra, $errors];
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
    private function recurrence(string $freq, array $values, CalendarSystemContract $impl, string $startIso, CalendarSystem $system, string $tz, array &$errors): ?array
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

    private function resolveModelDefinition(CalendarDefinition $definition, string $sourceKey): ?CalendarModelDefinition
    {
        if (! $this->models->has($sourceKey)) {
            return null;
        }
        $def = $this->models->definition($sourceKey);
        $configuredProvider = $definition->sources[$sourceKey] ?? null;
        $expectedProvider = $def->providerClass ?? \Karnoweb\Calendar\Providers\EloquentEventProvider::class;
        if ($configuredProvider !== null && $configuredProvider !== $expectedProvider && ! is_a($configuredProvider, \Karnoweb\Calendar\Providers\EloquentEventProvider::class, true)) {
            return null;
        }

        return $def;
    }
}
