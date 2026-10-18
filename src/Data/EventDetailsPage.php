<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;

final readonly class EventDetailsPage
{
    /** @param list<array{label:string, value:string}> $rows */
    public function __construct(public array $rows, public bool $hasMore = false)
    {
        if (count($rows) > 20) {
            throw new InvalidArgumentException('A details section page supports at most 20 rows.');
        }
        foreach ($rows as $row) {
            if (! is_string($row['label'] ?? null) || ! is_string($row['value'] ?? null)
                || mb_strlen($row['label']) > 191 || mb_strlen($row['value']) > 4000) {
                throw new InvalidArgumentException('Invalid details row.');
            }
        }
    }
}
