<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

/**
 * Session-level context resolved server-side and ready to be persisted onto a
 * new session. Built lazily by the session context enricher, consumed by the
 * session write repository.
 */
final readonly class IngestionContext
{
    public function __construct(
        public bool $isBot = false,
        public ?string $ip = null,
        public ?string $country = null,
        public ?string $region = null,
        public ?string $city = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $deviceType = null,
        public ?string $deviceBrand = null,
        public ?string $deviceModel = null,
        public ?string $browser = null,
        public ?string $browserVersion = null,
        public ?string $os = null,
        public ?string $osVersion = null,
        public ?string $referrer = null,
        public ?string $source = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $utmContent = null,
        public ?string $utmTerm = null,
        public ?string $landingRoute = null,
        public ?string $landingUrl = null,
        public ?string $mktCampaign = null,
        public ?string $mktAd = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
    ) {}
}
