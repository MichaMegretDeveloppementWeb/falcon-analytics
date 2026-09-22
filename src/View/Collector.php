<?php

declare(strict_types=1);

namespace Falcon\Analytics\View;

use Falcon\Analytics\Facades\Analytics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/** @internal what a host writes is the `@analyticsCollector` directive. */
final class Collector
{
    /**
     * What `@analyticsCollector` needs to know, or null when nothing is to be
     * measured on this page.
     *
     * **Null means tracking is cut**, and the view then declares nothing at all:
     * no script is brought in, and the collector's own guard — it reads
     * `window.__falconAnalytics` and leaves when the object is absent — is never
     * even reached. The host writes no condition of its own.
     *
     * Two of these values cannot be bundled, and that is why they travel inline
     * rather than inside the compiled file: the current route's name changes on
     * every page, and the cut applies per request.
     *
     * Runs on every host page, so any failure degrades to a page without
     * measurement rather than to a broken page.
     */
    public static function configuration(): ?string
    {
        try {
            // Suppressed when tracking is off or the current context is excluded
            // (e.g. an authenticated admin), so no collector runs on those pages.
            if (config('analytics.enabled') !== true || Analytics::isExcluded()) {
                return null;
            }

            return json_encode([
                'endpoint' => route('analytics.web.ingest', [], absolute: false),
                'route' => Route::currentRouteName(),
                'heartbeat' => (int) config('analytics.session.heartbeat_seconds') * 1000,
                'flush' => (int) config('analytics.session.flush_seconds') * 1000,
            ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Collector.configuration_failed', ['exception' => $e->getMessage()]);

            return null;
        }
    }
}
