<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

final readonly class CalendarFormResult
{
    /**
     * @param  array<string, mixed>  $values  sanitised values (valid) or the preserved raw input (invalid)
     * @param  array<string, list<string>|string>  $errors
     */
    public function __construct(
        public bool $valid,
        public array $values,
        public array $errors = [],
        public ?CreateEventInput $input = null,
    ) {}
}
