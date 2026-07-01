<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Falcon\Analytics\DTOs\Acquisition;

final readonly class SourceResolver
{
    /** @var list<string> */
    private const SEARCH_ENGINES = ['google', 'bing', 'yahoo', 'duckduckgo', 'ecosia', 'qwant', 'baidu', 'yandex'];

    /** @var list<string> */
    private const SOCIAL_NETWORKS = ['facebook', 'instagram', 'twitter', 'x.com', 'linkedin', 'tiktok', 'youtube', 'pinterest', 'reddit'];

    public function resolve(?string $landingUrl, ?string $referrer, ?string $appHost): Acquisition
    {
        $utm = $this->parseUtm($landingUrl);

        return new Acquisition(
            source: $this->classify($utm['medium'], $referrer, $appHost),
            utmSource: $utm['source'],
            utmMedium: $utm['medium'],
            utmCampaign: $utm['campaign'],
            utmContent: $utm['content'],
            utmTerm: $utm['term'],
        );
    }

    /**
     * @return array{source: ?string, medium: ?string, campaign: ?string, content: ?string, term: ?string}
     */
    private function parseUtm(?string $url): array
    {
        $query = $url !== null ? (parse_url($url, PHP_URL_QUERY) ?: '') : '';
        parse_str($query, $params);

        return [
            'source' => $this->clean($params['utm_source'] ?? null),
            'medium' => $this->clean($params['utm_medium'] ?? null),
            'campaign' => $this->clean($params['utm_campaign'] ?? null),
            'content' => $this->clean($params['utm_content'] ?? null),
            'term' => $this->clean($params['utm_term'] ?? null),
        ];
    }

    private function classify(?string $utmMedium, ?string $referrer, ?string $appHost): string
    {
        if ($utmMedium !== null) {
            return match (strtolower($utmMedium)) {
                'cpc', 'ppc', 'paid', 'paidsearch', 'display', 'banner' => 'paid',
                'organic' => 'organic',
                'social', 'social-media' => 'social',
                'email', 'newsletter' => 'email',
                'referral' => 'referral',
                default => 'campaign',
            };
        }

        $host = $referrer !== null ? parse_url($referrer, PHP_URL_HOST) : null;

        if ($host === null || $host === '') {
            return 'direct';
        }

        if ($appHost !== null && $this->sameHost($host, $appHost)) {
            return 'direct';
        }

        $host = strtolower($host);

        foreach (self::SEARCH_ENGINES as $engine) {
            if (str_contains($host, $engine)) {
                return 'organic';
            }
        }

        foreach (self::SOCIAL_NETWORKS as $network) {
            if (str_contains($host, $network)) {
                return 'social';
            }
        }

        return 'referral';
    }

    private function sameHost(string $a, string $b): bool
    {
        $normalise = fn (string $host): string => (string) preg_replace('/^www\./i', '', strtolower($host));

        return $normalise($a) === $normalise($b);
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
