<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Support;

use Karnoweb\Calendar\Data\CalendarDefinition;

/** Source toggles and declared filters; all values are checked against the server-side declaration. */
final class CalendarFilterEngine
{
    /**
     * @param  list<string>  $disabled
     * @return list<string> enabled source keys
     */
    public function enabledSources(CalendarDefinition $definition, array $disabled): array
    {
        $enabled = [];
        foreach ($definition->sourceKeys() as $key) {
            if ($definition->source($key)->visible && ! in_array($key, $disabled, true)) {
                $enabled[] = $key;
            }
        }

        return $enabled;
    }

    /**
     * Toggle a source off/on. When requireOneSource is set the last enabled source can never be disabled.
     *
     * @param  list<string>  $disabled
     * @return list<string> new disabled list
     */
    public function toggle(CalendarDefinition $definition, array $disabled, string $sourceKey): array
    {
        if (! $definition->hasSource($sourceKey)) {
            return $this->clean($definition, $disabled);
        }
        $disabled = $this->clean($definition, $disabled);
        if (in_array($sourceKey, $disabled, true)) {
            return array_values(array_diff($disabled, [$sourceKey]));
        }
        $next = [...$disabled, $sourceKey];
        if ($definition->settings->requireOneSource && $this->enabledSources($definition, $next) === []) {
            return $disabled;
        }

        return $next;
    }

    /**
     * @param  list<mixed>  $disabled
     * @return list<string>
     */
    public function clean(CalendarDefinition $definition, array $disabled): array
    {
        $out = [];
        foreach ($disabled as $key) {
            if (is_string($key) && $definition->hasSource($key)) {
                $out[$key] = $key;
            }
        }
        $out = array_values($out);
        if ($definition->settings->requireOneSource && $this->enabledSources($definition, $out) === []) {
            return [];
        }

        return $out;
    }
}
