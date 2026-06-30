<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

/**
 * Locality resolved from an IP. All fields null when geolocation is unavailable
 * (no database configured, private/unknown IP).
 */
final readonly class GeoLocation
{
    public function __construct(
        public ?string $country = null,
        public ?string $region = null,
        public ?string $city = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
