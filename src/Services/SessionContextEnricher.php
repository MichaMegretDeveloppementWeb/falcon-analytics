<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IngestionContext;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Support\GeoResolver;
use Falcon\Analytics\Support\UserAgentParser;

final readonly class SessionContextEnricher
{
    public function __construct(
        private UserAgentParser $userAgent,
        private GeoResolver $geo,
        private SourceResolver $source,
    ) {}

    /**
     * Resolve the geo/device/acquisition context for a NEW session. This is the
     * heavy work (device-detector parsing, GeoIP lookup), so callers run it only
     * when a session is actually started, never on every in-session beacon.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    public function enrich(RequestSnapshot $snapshot, IncomingBatch $batch, ?array $subject): IngestionContext
    {
        $geo = $this->geo->locate($snapshot->ip);
        $device = $this->userAgent->parse($snapshot->userAgent);

        $landing = $batch->events[0] ?? null;
        $acquisition = $this->source->resolve($landing?->url, $batch->referrer, $snapshot->host);

        return new IngestionContext(
            isBot: $device->isBot,
            ip: $this->storableIp($snapshot->ip),
            country: $geo->country,
            region: $geo->region,
            city: $geo->city,
            latitude: $geo->latitude,
            longitude: $geo->longitude,
            deviceType: $device->deviceType,
            deviceBrand: $device->deviceBrand,
            deviceModel: $device->deviceModel,
            browser: $device->browser,
            browserVersion: $device->browserVersion,
            os: $device->os,
            osVersion: $device->osVersion,
            referrer: $batch->referrer,
            source: $acquisition->source,
            utmSource: $acquisition->utmSource,
            utmMedium: $acquisition->utmMedium,
            utmCampaign: $acquisition->utmCampaign,
            utmContent: $acquisition->utmContent,
            utmTerm: $acquisition->utmTerm,
            landingRoute: $landing?->route,
            landingUrl: $landing?->url,
            subjectType: $subject['type'] ?? null,
            subjectId: $subject['id'] ?? null,
        );
    }

    private function storableIp(?string $ip): ?string
    {
        if ($ip === null || ! config('analytics.privacy.anonymize_ip')) {
            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $octets = explode('.', $ip);
            $octets[3] = '0';

            return implode('.', $octets);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = inet_pton($ip);

            return $packed === false ? null : (string) inet_ntop(substr($packed, 0, 6).str_repeat("\0", 10));
        }

        return $ip;
    }
}
