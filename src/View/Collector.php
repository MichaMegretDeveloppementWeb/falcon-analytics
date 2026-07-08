<?php

declare(strict_types=1);

namespace Falcon\Analytics\View;

use Falcon\Analytics\Facades\Analytics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

final class Collector
{
    private static ?string $version = null;

    /**
     * The markup emitted by @analyticsScripts: an inline config object plus a
     * cached script tag. Empty when tracking is disabled. Runs inline on every host
     * page, so any failure degrades to an empty string rather than breaking the host.
     */
    public static function render(): string
    {
        try {
            // Suppressed when tracking is off or the current context is excluded
            // (e.g. an authenticated admin), so no collector loads on those pages.
            if (! config('analytics.enabled') || Analytics::isExcluded()) {
                return '';
            }

            $endpoint = '/'.ltrim((string) config('analytics.endpoint'), '/');

            $config = json_encode([
                'endpoint' => $endpoint,
                'route' => Route::currentRouteName(),
                'heartbeat' => (int) config('analytics.session.heartbeat_seconds') * 1000,
                'flush' => (int) config('analytics.session.flush_seconds') * 1000,
            ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            $script = e($endpoint.'.js?v='.self::version());

            return "<script>window.__falconAnalytics={$config};</script>".PHP_EOL
                ."<script src=\"{$script}\" defer></script>";
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Collector.render_failed', ['exception' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Content hash of the collector script, appended to its URL so a long,
     * immutable cache is busted automatically whenever the script changes.
     */
    private static function version(): string
    {
        if (self::$version === null) {
            $path = dirname(__DIR__, 2).'/resources/js/collector.js';
            self::$version = is_file($path) ? substr((string) md5_file($path), 0, 12) : 'dev';
        }

        return self::$version;
    }
}
