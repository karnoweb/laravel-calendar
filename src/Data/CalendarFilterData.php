<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;
use Karnoweb\Calendar\Security\SafeCalendarMarkup;

/** A server-declared filter. URL/Livewire values can only select among `options`; they never create filters. */
final readonly class CalendarFilterData
{
    public string $label;

    /** @var array<string, string> value => label */
    public array $options;

    /** @param array<string, string> $options value => label */
    public function __construct(
        public string $sourceKey,
        public string $field,
        string $label,
        array $options,
        public ?string $default = null,
    ) {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $sourceKey) || ! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $field)) {
            throw new InvalidArgumentException('Invalid filter source or field.');
        }
        if ($options === []) {
            throw new InvalidArgumentException('Filter requires at least one allowed value.');
        }
        $clean = [];
        foreach ($options as $value => $optionLabel) {
            $clean[(string) $value] = SafeCalendarMarkup::text((string) $optionLabel, 80);
        }
        if ($default !== null && ! array_key_exists($default, $clean)) {
            throw new InvalidArgumentException('Filter default is not an allowed value.');
        }
        $this->label = SafeCalendarMarkup::text($label, 80);
        $this->options = $clean;
    }

    /** Request key used in URLs and Livewire state: "{source}.{field}". */
    public function key(): string
    {
        return $this->sourceKey.'.'.$this->field;
    }

    /** @return list<string> */
    public function allowedValues(): array
    {
        return array_map('strval', array_keys($this->options));
    }
}
