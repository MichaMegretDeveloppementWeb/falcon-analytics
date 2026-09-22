<?php

declare(strict_types=1);

namespace Falcon\Analytics\Events;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Holds the tracked events declared by the host's events file. The file is
 * required once (lazily, when the registry is first resolved); TrackedEvent::define
 * registers into the registry that is currently loading. Mirrors FunnelRegistry.
 *
 * @internal what a host writes is `TrackedEvent::define()`, in the declaration
 *           file · this is what gathers them afterwards.
 */
final class EventRegistry
{
    private static ?self $loading = null;

    /** @var array<string, TrackedEvent> */
    private array $events = [];

    private ?string $failure = null;

    /**
     * Load the host events file. A malformed file is logged and degraded to
     * whatever was declared before the failure: it must never break the request
     * that resolves the registry. The failure is kept, so the screens and the
     * diagnostic can say what the log alone would.
     */
    public function load(string $path): void
    {
        self::$loading = $this;

        try {
            require $path;
        } catch (Throwable $e) {
            $this->failure = $path.' · '.$e->getMessage();

            Log::channel(config('analytics.log_channel'))->error('Analytics events file failed to load.', [
                'exception' => $e,
            ]);
        } finally {
            self::$loading = null;
        }
    }

    /** The file and the error it stopped on, or null when it loaded whole. */
    public function failure(): ?string
    {
        return $this->failure;
    }

    public static function current(): ?self
    {
        return self::$loading;
    }

    public function register(TrackedEvent $event): void
    {
        $this->events[$event->name] = $event;
    }

    /** @return list<TrackedEvent> */
    public function all(): array
    {
        return array_values($this->events);
    }

    public function get(string $name): ?TrackedEvent
    {
        return $this->events[$name] ?? null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->events);
    }
}
