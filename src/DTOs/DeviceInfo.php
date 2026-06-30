<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

/**
 * Parsed user agent. isBot short-circuits the rest: when true, the device fields
 * are left null because a bot's client/os details are not meaningful.
 */
final readonly class DeviceInfo
{
    public function __construct(
        public bool $isBot = false,
        public ?string $deviceType = null,
        public ?string $deviceBrand = null,
        public ?string $deviceModel = null,
        public ?string $browser = null,
        public ?string $browserVersion = null,
        public ?string $os = null,
        public ?string $osVersion = null,
    ) {}
}
