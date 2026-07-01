<?php

declare(strict_types=1);

namespace Falcon\Analytics\View;

use Falcon\Analytics\Facades\Analytics;
use Illuminate\Support\Facades\Route;

final class Collector
{
    /**
     * The markup emitted by @analyticsScripts: an inline config object plus a
     * cached script tag. Empty when tracking is disabled.
     */
    public static function render(): string
    {
        // Suppressed when tracking is off or the current context is excluded
        // (e.g. an authenticated admin), so no collector loads on those pages.
        if (! config('analytics.enabled') || Analytics::excluded()) {
            return '';
        }

        $endpoint = '/'.ltrim((string) config('analytics.endpoint'), '/');

        $config = json_encode([
            'endpoint' => $endpoint,
            'route' => Route::currentRouteName(),
            'heartbeat' => (int) config('analytics.session.heartbeat_seconds') * 1000,
            'flush' => 5000,
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $script = e($endpoint.'.js');

        return "<script>window.__falconAnalytics={$config};</script>".PHP_EOL
            ."<script src=\"{$script}\" defer></script>";
    }
}
