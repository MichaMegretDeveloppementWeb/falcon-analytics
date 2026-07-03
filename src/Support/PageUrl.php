<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Illuminate\Support\Facades\Route;

/**
 * A clean, human path for a tracked page. The stored URL wins (its real dynamic
 * value, minus domain and query), falling back to the route's URI pattern and
 * finally the raw route name.
 */
final class PageUrl
{
    public static function resolve(?string $route, ?string $url): string
    {
        if ($url !== null && $url !== '') {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        if ($route !== null && $route !== '') {
            $uri = Route::getRoutes()->getByName($route)?->uri();

            if ($uri === null) {
                return $route;
            }

            // No concrete URL to show (e.g. an aggregated route): render the
            // pattern cleanly, replacing {param} placeholders with an ellipsis
            // rather than exposing the raw template.
            return '/'.ltrim(preg_replace('/\{[^}]+\}/', '…', $uri) ?? $uri, '/');
        }

        return '';
    }
}
