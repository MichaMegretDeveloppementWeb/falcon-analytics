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

    public function __construct(private readonly ?string $databasePath = null) {}

    public function locate(?string $ip): GeoLocation
    {
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
