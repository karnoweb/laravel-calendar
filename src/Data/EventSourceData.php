<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Data;

use InvalidArgumentException;
use Karnoweb\Calendar\Security\AllowedCssToken;
use Karnoweb\Calendar\Security\SafeCalendarMarkup;

final readonly class EventSourceData
{
    public string $label;

    public string $color;

    public ActionCapabilities $capabilities;

    public function __construct(
        public string $key,
        string $label,
        string $color = 'blue',
        public bool $readOnly = false,
        ?ActionCapabilities $capabilities = null,
        public bool $visible = true,
    ) {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $key)) {
            throw new InvalidArgumentException('Invalid source key.');
        }
        if (! AllowedCssToken::isValidColor($color)) {
            throw new InvalidArgumentException('Source colour is not an allowed token.');
        }
        $this->label = SafeCalendarMarkup::text($label === '' ? $key : $label, 80);
        $this->color = strtolower($color);
        $this->capabilities = $readOnly
            ? ActionCapabilities::none()
            : ($capabilities ?? ActionCapabilities::all());
    }
}
