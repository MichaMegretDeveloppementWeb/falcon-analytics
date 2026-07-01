<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * Redact sensitive query parameters (tokens, emails, secrets) from stored URLs
 * while keeping tracking parameters (utm_*, gclid, fbclid, custom ad params)
 * intact. The denylist is configured under analytics.privacy.redact_query_params.
 */
final readonly class UrlRedactor
{
    public function redact(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return $url;
        }

        $denied = array_map('strtolower', (array) config('analytics.privacy.redact_query_params', []));

        if ($denied === []) {
            return $url;
        }

        parse_str($query, $params);
        $changed = false;

        foreach ($params as $key => $value) {
            if (in_array(strtolower((string) $key), $denied, true)) {
                $params[$key] = 'redacted';
                $changed = true;
            }
        }

        if (! $changed) {
            return $url;
        }

        $position = strpos($url, '?');
        $base = $position === false ? $url : substr($url, 0, $position);

        return $base.'?'.http_build_query($params);
    }
}
