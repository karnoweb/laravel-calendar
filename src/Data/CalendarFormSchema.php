<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;

/**
 * Declarative form definition supplied by the host (or the default resolver).
 * Field types are a closed set so views never render arbitrary HTML.
 *
 * Each field: name, type (text|textarea|date|time|checkbox|select|number), label (translation key or text),
 * required, options (value => label, for select), max (length), default.
 */
final readonly class CalendarFormSchema
{
    public const TYPES = ['text', 'textarea', 'date', 'time', 'checkbox', 'select', 'number'];

    /** @param list<array{name:string,type:string,label:string,required?:bool,options?:array<string,string>,max?:int,default?:mixed,group?:string}> $fields */
    public function __construct(
        public string $sourceKey,
        public array $fields,
        public bool $supportsRecurrence = true,
        public string $titleKey = 'calendar::calendar.form.new_event',
    ) {
        $names = [];
        foreach ($fields as $field) {
            if (! preg_match('/^[a-z][A-Za-z0-9_]{0,39}$/', $field['name'] ?? '') || ! in_array($field['type'] ?? '', self::TYPES, true)) {
                throw new InvalidArgumentException('Invalid form field.');
            }
            if (isset($names[$field['name']])) {
                throw new InvalidArgumentException('Duplicate form field.');
            }
            $names[$field['name']] = true;
        }
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_column($this->fields, 'name');
    }
}
