<?php

declare(strict_types=1);

namespace Falcon\Analytics\Funnels;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Holds the funnels declared by the host's funnels file. The file is required
 * once (lazily, when the registry is first resolved); Funnel::define registers
 * into the registry that is currently loading.
 */
final class FunnelRegistry
{
    private static ?self $loading = null;

    /** @var array<string, Funnel> */
    private array $funnels = [];

    /**
     * Load the host funnels file. A malformed file is logged and degraded to
     * whatever was declared before the failure: it must never break the request
     * that resolves the registry.
     */
    public function load(string $path): void
    {
        self::$loading = $this;

        try {
            require $path;
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics funnels file failed to load.', [
                'exception' => $e,
            ]);
        } finally {
            self::$loading = null;
        }
    }

    public static function current(): ?self
    {
        return self::$loading;
    }

    public function register(Funnel $funnel): void
    {
        $this->funnels[$funnel->key] = $funnel;
    }

    /** @return list<Funnel> */
    public function all(): array
    {
        return array_values($this->funnels);
    }

    public function get(string $key): ?Funnel
    {
        return $this->funnels[$key] ?? null;
    }
}
