<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Observability;

use Closure;
use Illuminate\Support\Str;
use Karnoweb\Calendar\Contracts\CalendarObserver;
use Throwable;

/** Measures duration/counts/errors. Never receives titles, descriptions or other private text. */
class CalendarTelemetry
{
    public function __construct(private readonly CalendarObserver $observer) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  array<string, scalar|null>  $tags
     * @return T
     */
    public function measure(string $name, Closure $callback, array $tags = []): mixed
    {
        $start = hrtime(true);
        try {
            $result = $callback();
        } catch (Throwable $e) {
            $this->observer->observe($name.'.error', $tags + [
                'duration_ms' => round((hrtime(true) - $start) / 1e6, 2),
                'exception' => $e::class,
                'correlation_id' => $this->correlationId(),
            ]);
            throw $e;
        }
        $this->observer->observe($name, $tags + ['duration_ms' => round((hrtime(true) - $start) / 1e6, 2)]);

        return $result;
    }

    /** @param array<string, scalar|null> $data */
    public function record(string $name, array $data = []): void
    {
        $this->observer->observe($name, $data);
    }

    public function correlationId(): string
    {
        return (string) Str::uuid();
    }
}
