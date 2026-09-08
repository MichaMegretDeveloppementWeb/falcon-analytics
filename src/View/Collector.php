<?php

declare(strict_types=1);

namespace Falcon\Analytics\View;

use Falcon\Analytics\Facades\Analytics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

final class Collector
{
    /**
     * The markup emitted by @analyticsConfig: an inline config object, and
     * nothing else.
     *
     * The collector's code lives in the host's public JavaScript entrypoint,
     * which its build names, versions and serves. What stays here cannot be
     * bundled: the route name changes on every page, and tracking is cut while
     * an excluded guard is authenticated.
     *
     * Emitting nothing amounts to tracking cut: the collector reads
     * `window.__falconAnalytics` and exits on its own when it is absent, so the
     * host writes no condition of its own.
     *
     * Runs inline on every host page, so any failure degrades to an empty
     * string rather than breaking the host.
     */
    public static function render(): string
    {
        try {
            // Suppressed when tracking is off or the current context is excluded
            // (e.g. an authenticated admin), so no collector runs on those pages.
            if (! config('analytics.enabled') || Analytics::isExcluded()) {
                return '';
            }

            $config = json_encode([
                'endpoint' => '/'.ltrim((string) config('analytics.endpoint'), '/'),
                'route' => Route::currentRouteName(),
                'heartbeat' => (int) config('analytics.session.heartbeat_seconds') * 1000,
                'flush' => (int) config('analytics.session.flush_seconds') * 1000,
            ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            return "<script>window.__falconAnalytics={$config};</script>";
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Collector.render_failed', ['exception' => $e->getMessage()]);

            return '';
        }
    }
}
