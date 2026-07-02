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

    /**
     * Ad-platform click identifiers that unambiguously mark paid traffic even
     * without UTM tags (auto-tagging). fbclid is deliberately excluded: Facebook
     * appends it to every outbound click, organic ones included.
     *
     * @var list<string>
     */
    private const PAID_CLICK_IDS = ['gclid', 'gbraid', 'wbraid', 'dclid', 'msclkid', 'ttclid'];

    /** @var list<string> */
    private const PAID_MEDIUMS = ['cpc', 'ppc', 'paid', 'paidsearch', 'paid-search', 'paid_social', 'paidsocial', 'social-paid', 'display', 'banner', 'cpm', 'cpv', 'retargeting'];

    public function resolve(?string $landingUrl, ?string $referrer, ?string $appHost): Acquisition
    {
        $params = $this->queryParams($landingUrl);
        $utm = $this->extractUtm($params);

        return new Acquisition(
            source: $this->classify($params, $utm['medium'], $referrer, $appHost),
            utmSource: $utm['source'],
            utmMedium: $utm['medium'],
            utmCampaign: $utm['campaign'],
            utmContent: $utm['content'],
            utmTerm: $utm['term'],
        );
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

    /**
     * @param  array<string, mixed>  $params
     * @return array{source: ?string, medium: ?string, campaign: ?string, content: ?string, term: ?string}
     */
    private function extractUtm(array $params): array
    {
        return [
            'source' => $this->clean($params['utm_source'] ?? null),
            'medium' => $this->clean($params['utm_medium'] ?? null),
            'campaign' => $this->clean($params['utm_campaign'] ?? null),
            'content' => $this->clean($params['utm_content'] ?? null),
            'term' => $this->clean($params['utm_term'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function classify(array $params, ?string $utmMedium, ?string $referrer, ?string $appHost): string
    {
        // Ad click IDs (Google/Bing/TikTok auto-tagging) are the strongest paid signal.
        if ($this->hasPaidClickId($params)) {
            return 'paid';
        }

        if ($utmMedium !== null) {
            $medium = strtolower($utmMedium);

            if (in_array($medium, self::PAID_MEDIUMS, true)) {
                return 'paid';
            }

            return match ($medium) {
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

    /**
     * @param  array<string, mixed>  $params
     */
    private function hasPaidClickId(array $params): bool
    {
        foreach (self::PAID_CLICK_IDS as $key) {
            if ($this->clean($params[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
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
