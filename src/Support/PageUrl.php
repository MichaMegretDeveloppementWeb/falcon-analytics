<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Illuminate\Support\Facades\Route;

/**
 * A clean, human path for a tracked page. The stored URL wins (its real dynamic
 * value, minus domain, query and fragment), falling back to the route's URI pattern and
 * finally the raw route name.
 *
 * @internal
 */
final class PageUrl
{
    public static function resolve(?string $route, ?string $url): string
    {
        $path = StoredUrl::page($url);

        if ($path !== null) {
            return $path;
        }

        if ($route !== null && $route !== '') {
            $uri = Route::getRoutes()->getByName($route)?->uri();

            if ($uri === null) {
                return $route;
            }

            // No concrete URL to show: the pattern, with an ellipsis for each placeholder.
            return '/'.ltrim(preg_replace('/\{[^}]+\}/', '…', $uri) ?? $uri, '/');
        }

        return '';
    }
}
