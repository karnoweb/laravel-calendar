<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;

/** Built exclusively on the server. Never serialised into Livewire state or the URL. */
final readonly class CalendarContext
{
    public function __construct(
        public string $calendarKey,
        public ?string $actorId,
        public string $scopeKey,
        public string $locale,
        public string $timezone,
        public string $instanceId,
    ) {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $calendarKey)) {
            throw new InvalidArgumentException('Invalid calendar key.');
        }
        if ($scopeKey === '' || strlen($scopeKey) > 191) {
            throw new InvalidArgumentException('Scope key is required.');
        }
        if ($actorId !== null && ($actorId === '' || strlen($actorId) > 191)) {
            throw new InvalidArgumentException('Invalid actor id.');
        }
        if ($locale === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Invalid locale or timezone.');
        }
    }

    public function isAnonymous(): bool
    {
        return $this->actorId === null;
    }

    public function withTimezone(string $timezone): self
    {
        return new self($this->calendarKey, $this->actorId, $this->scopeKey, $this->locale, $timezone, $this->instanceId);
    }

    public function withLocale(string $locale): self
    {
        return new self($this->calendarKey, $this->actorId, $this->scopeKey, $locale, $this->timezone, $this->instanceId);
    }

    public function withCalendarKey(string $calendarKey): self
    {
        return new self($calendarKey, $this->actorId, $this->scopeKey, $this->locale, $this->timezone, $this->instanceId);
    }
}
