<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

/**
 * Extracts the landing URL's query parameters (bounded), stored verbatim on the
 * session so any marketing (param, value) condition can be matched against them
 * at report time. Definition-free: the campaign/ad rules live in the dashboard.
 *
 * @internal
 */
final readonly class LandingParamsResolver
{
    private const MAX_PARAMS = 30;

    private const MAX_KEY_LENGTH = 100;

    private const MAX_VALUE_LENGTH = 150;

    /**
     * @return array<string, string>
     */
    public function resolve(?string $landingUrl): array
    {
        // `parse_url` gives `null` when the URL has no query part, and `false`
        // when it is malformed: both mean "nothing to read".
        $query = $landingUrl === null ? null : parse_url($landingUrl, PHP_URL_QUERY);
        $query = is_string($query) ? $query : '';
        parse_str($query, $params);

        $result = [];

        foreach ($params as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $key = trim((string) $key);
            $value = trim($value);

            if ($key === '' || $value === '') {
                continue;
            }

            $result[mb_substr($key, 0, self::MAX_KEY_LENGTH)] = mb_substr($value, 0, self::MAX_VALUE_LENGTH);

            if (count($result) >= self::MAX_PARAMS) {
                break;
            }
        }

        return $result;
    }
}
