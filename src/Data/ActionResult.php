<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use Karnoweb\Calendar\Enums\ActionStatus;

final readonly class ActionResult
{
    /**
     * @param  array<string, list<string>|string>  $fieldErrors
     * @param  list<EventReference>  $changedEvents
     * @param  list<DateRange>  $refreshRanges
     */
    public function __construct(
        public ActionStatus $status,
        public string $messageKey = '',
        public array $fieldErrors = [],
        public array $changedEvents = [],
        public array $refreshRanges = [],
        public ?string $newVersion = null,
    ) {}

    /**
     * @param  list<EventReference>  $changed
     * @param  list<DateRange>  $refresh
     */
    public static function success(array $changed = [], array $refresh = [], ?string $newVersion = null, string $messageKey = 'calendar::calendar.messages.success'): self
    {
        return new self(ActionStatus::Success, $messageKey, [], $changed, $refresh, $newVersion);
    }

    /** @param  array<string, list<string>|string>  $fieldErrors */
    public static function failure(ActionStatus $status, string $messageKey = '', array $fieldErrors = []): self
    {
        return new self($status, $messageKey, $fieldErrors);
    }

    public function isSuccess(): bool
    {
        return $this->status === ActionStatus::Success;
    }
}
