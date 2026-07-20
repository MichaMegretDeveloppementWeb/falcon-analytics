<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Falcon\Analytics\DTOs\GeoLocation;
use GeoIp2\Database\Reader;
use Throwable;

final class GeoResolver
{
    private ?Reader $reader = null;

    private bool $opened = false;

    /**
     * @param  string|null  $devIp  Public IP substituted for private/reserved
     *                              request IPs (local development, where every
     *                              request comes from 127.0.0.1). Inert in
     *                              production by design: real public IPs are
     *                              never overridden.
     */
    public function __construct(
        private readonly ?string $databasePath = null,
        private readonly ?string $devIp = null,
    ) {}

    public function locate(?string $ip): GeoLocation
    {
        $ip = $this->effectiveIp($ip);

        if ($ip === null || $ip === '') {
            return new GeoLocation;
        }

        $reader = $this->reader();

        if ($reader === null) {
            return new GeoLocation;
        }

        try {
            $record = $reader->city($ip);

            return new GeoLocation(
                country: $record->country->isoCode,
                region: $record->mostSpecificSubdivision->name,
                city: $record->city->name,
                latitude: $record->location->latitude,
                longitude: $record->location->longitude,
            );
        } catch (Throwable) {
            // Private/unknown IP or corrupt record: degrade to no location.
            return new GeoLocation;
        }
    }

    /**
     * The IP actually resolved: the request one, except that a private or
     * reserved IP is replaced by the configured development IP when one is set.
     */
    public function effectiveIp(?string $ip): ?string
    {
        if ($this->devIp === null || $this->devIp === '' || $ip === null || $ip === '') {
            return $ip;
        }

        $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        return $isPublic ? $ip : $this->devIp;
    }

    /**
     * Open the database once and reuse the memory-mapped reader. Returns null
     * when no usable database is configured, so geolocation degrades silently.
     */
    private function reader(): ?Reader
    {
        if (! $this->opened) {
            $this->opened = true;

            if ($this->databasePath !== null && is_file($this->databasePath)) {
                try {
                    $this->reader = new Reader($this->databasePath);
                } catch (Throwable) {
                    $this->reader = null;
                }
            }
        }

        return $this->reader;
    }
}
