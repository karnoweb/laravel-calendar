<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;

final readonly class EventQuery
{
    /** @param array<string, string|list<string>> $filters already allowlisted for the target source */
    public function __construct(
        public DateRange $range,
        public int $limit = 100,
        public ?string $cursor = null,
        public string $search = '',
        public array $filters = [],
        public string $requestKey = '',
    ) {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Query limit out of range.');
        }
    }

    public function withCursor(?string $cursor): self
    {
        return new self($this->range, $this->limit, $cursor, $this->search, $this->filters, $this->requestKey);
    }
}
