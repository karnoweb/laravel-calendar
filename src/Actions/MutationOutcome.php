<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Actions;

use Karnoweb\Calendar\Data\ActionResult;

final readonly class MutationOutcome
{
    /** @param array{type:string, status:string, message:string, fieldErrors:array<string, string>} $presentation */
    public function __construct(
        public ActionResult $result,
        public array $presentation,
        public bool $refetch,
        public bool $closeForm,
    ) {}

    public function isSuccess(): bool
    {
        return $this->result->isSuccess();
    }
}
