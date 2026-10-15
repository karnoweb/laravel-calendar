<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Enums;

/** Closed allowlist of operations the browser may request. */
enum CalendarAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Move = 'move';
    case Resize = 'resize';
    case SeriesUpdate = 'series.update';
    case SeriesDelete = 'series.delete';
    case OccurrenceUpdate = 'occurrence.update';
    case OccurrenceDelete = 'occurrence.delete';
    case SeriesSplit = 'series.split';

    public function requiresReference(): bool
    {
        return $this !== self::Create;
    }

    /** Capability flag on ActionCapabilities required for this action. */
    public function capability(): string
    {
        return match ($this) {
            self::Create => 'canCreate',
            self::Update, self::SeriesUpdate, self::OccurrenceUpdate, self::SeriesSplit => 'canEdit',
            self::Delete, self::SeriesDelete, self::OccurrenceDelete => 'canDelete',
            self::Move => 'canMove',
            self::Resize => 'canResize',
        };
    }

    /** Feature flag (CalendarSettings::features) required for this action. */
    public function feature(): string
    {
        return match ($this) {
            self::Create => 'create',
            self::Update, self::SeriesUpdate, self::OccurrenceUpdate, self::SeriesSplit => 'edit',
            self::Delete, self::SeriesDelete, self::OccurrenceDelete => 'delete',
            self::Move => 'move',
            self::Resize => 'resize',
        };
    }

    public function isRecurrenceAction(): bool
    {
        return in_array($this, [self::SeriesUpdate, self::SeriesDelete, self::OccurrenceUpdate, self::OccurrenceDelete, self::SeriesSplit], true);
    }
}
