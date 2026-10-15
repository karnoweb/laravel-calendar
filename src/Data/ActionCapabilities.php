<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

/** Display hints only. The server re-validates every action against the host handler. */
final readonly class ActionCapabilities
{
    public function __construct(
        public bool $canCreate = false,
        public bool $canEdit = false,
        public bool $canDelete = false,
        public bool $canMove = false,
        public bool $canResize = false,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public static function all(): self
    {
        return new self(true, true, true, true, true);
    }

    public function allows(string $capability): bool
    {
        return match ($capability) {
            'canCreate' => $this->canCreate,
            'canEdit' => $this->canEdit,
            'canDelete' => $this->canDelete,
            'canMove' => $this->canMove,
            'canResize' => $this->canResize,
            default => false,
        };
    }

    public function isReadOnly(): bool
    {
        return ! ($this->canCreate || $this->canEdit || $this->canDelete || $this->canMove || $this->canResize);
    }
}
