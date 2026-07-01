<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Middleware;

use Closure;
use Falcon\Analytics\Facades\Analytics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the ingestion endpoint: silently drop (204) requests that are disabled,
 * cross-origin, or excluded (internal staff, listed IPs). Bots are NOT dropped
 * here, they are flagged during ingestion and filtered in the dashboard.
 */
final class EnsureAnalyticsAccepts
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldDrop($request)) {
            return response()->noContent();
        }

        return $next($request);
    }

    private function shouldDrop(Request $request): bool
    {
        return ! config('analytics.enabled')
            || ! $this->isSameOrigin($request)
            || Analytics::excluded()
            || IpUtils::checkIp((string) $request->ip(), config('analytics.exclude_ips', []));
    }

    /**
     * A forged cross-site beacon always carries an Origin (browsers set it on
     * cross-origin POST), so a mismatched host is rejected. A missing Origin and
     * Referer is allowed: it cannot be a browser cross-site forgery.
     */
    private function isSameOrigin(Request $request): bool
    {
        $source = $request->headers->get('Origin') ?? $request->headers->get('Referer');

        if ($source === null) {
            return true;
        }

        $sourceHost = parse_url($source, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($sourceHost === null || $appHost === null) {
            return true;
        }

        $normalise = fn (string $host): string => (string) preg_replace('/^www\./i', '', strtolower($host));

        return $normalise($sourceHost) === $normalise($appHost);
    }
}
