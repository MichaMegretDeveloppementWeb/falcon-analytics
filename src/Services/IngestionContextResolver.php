<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Falcon\Analytics\Analytics;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IngestionContext;
use Falcon\Analytics\Support\GeoResolver;
use Falcon\Analytics\Support\UserAgentParser;
use Illuminate\Http\Request;

final readonly class IngestionContextResolver
{
    public function __construct(
        private Analytics $analytics,
        private VisitorIdentityResolver $identity,
        private UserAgentParser $userAgent,
        private GeoResolver $geo,
        private SourceResolver $source,
    ) {}

    public function resolve(Request $request, IncomingBatch $batch): IngestionContext
    {
        $uuid = $this->identity->resolve($request, $this->analytics->consentGranted());
        $subject = $this->analytics->subject();

        $ip = $request->ip();
        $geo = $this->geo->locate($ip);
        $device = $this->userAgent->parse($request->userAgent());

        $landing = $batch->events[0] ?? null;
        $acquisition = $this->source->resolve($landing?->url, $batch->referrer, $request->getHost());

        return new IngestionContext(
            visitorUuid: $uuid,
            isBot: $device->isBot,
            ip: $this->storableIp($ip),
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
