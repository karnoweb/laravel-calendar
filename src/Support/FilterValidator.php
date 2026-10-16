<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Data\CalendarDefinition;

/**
 * Filters are server-declared. Client input can only pick among declared values;
 * unknown fields or values are silently dropped (never an error that could leak what exists).
 */
final class FilterValidator
{
    /**
     * @param  array<string, mixed>  $input  map "source.field" => value
     * @return array<string, string> sanitized map
     */
    public static function sanitize(CalendarDefinition $definition, array $input): array
    {
        $clean = [];
        $allowedByField = [];
        foreach ($definition->filters as $filter) {
            foreach ($filter->allowedValues() as $val) {
                $allowedByField[$filter->field][$val] = true;
            }
        }

        foreach ($definition->filters as $filter) {
            $scopedRaw = $input[$filter->key()] ?? null;
            if (is_string($scopedRaw) && in_array($scopedRaw, $filter->allowedValues(), true)) {
                $clean[$filter->key()] = $scopedRaw;

                continue;
            }
            $fieldRaw = $input[$filter->field] ?? null;
            if (is_string($fieldRaw) && isset($allowedByField[$filter->field][$fieldRaw])) {
                $clean[$filter->field] = $fieldRaw;
                $clean[$filter->key()] = $fieldRaw;

                continue;
            }
            if (is_string($filter->default) && in_array($filter->default, $filter->allowedValues(), true)) {
                $clean[$filter->key()] = $filter->default;
            }
        }

        return $clean;
    }

    /**
     * Select the filters that belong to one source, keyed by field name.
     *
     * @param  array<string, string>  $sanitized
     * @return array<string, string>
     */
    public static function forSource(string $sourceKey, array $sanitized): array
    {
        $out = [];
        $prefix = $sourceKey.'.';
        foreach ($sanitized as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $out[substr($key, strlen($prefix))] = $value;
            }
        }

        return $out;
    }
}
