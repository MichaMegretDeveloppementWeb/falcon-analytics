<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Falcon\Analytics\DTOs\AdTag;

/**
 * Extracts the raw campaign/ad identifiers from a landing URL's query string,
 * using the configured parameter names. Attribution to a named ad happens later,
 * at report time, so this stays a pure, definition-free parse.
 */
final readonly class AdTagResolver
{
    private const MAX_LENGTH = 150;

    public function resolve(?string $landingUrl): AdTag
    {
        $params = $this->queryParams($landingUrl);

        /** @var array{campaign?: string, ad?: string} $names */
        $names = config('analytics.marketing.params', []);

        return new AdTag(
            campaign: $this->value($params, $names['campaign'] ?? 'campaign'),
            ad: $this->value($params, $names['ad'] ?? 'ad'),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function value(array $params, string $key): ?string
    {
        $value = $params[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, self::MAX_LENGTH);
    }

    /**
     * @return array<string, mixed>
     */
    private function queryParams(?string $url): array
    {
        $query = $url !== null ? (parse_url($url, PHP_URL_QUERY) ?: '') : '';
        parse_str($query, $params);

        return $params;
    }
}
