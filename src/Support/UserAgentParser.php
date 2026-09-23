<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use DeviceDetector\DeviceDetector;
use Falcon\Analytics\DTOs\DeviceInfo;

/** @internal */
final readonly class UserAgentParser
{
    /** Session column lengths; an exotic UA is truncated so the insert never fails. */
    private const MAX_TYPE_LENGTH = 20;

    private const MAX_NAME_LENGTH = 60;

    private const MAX_VERSION_LENGTH = 30;

    public function parse(?string $userAgent): DeviceInfo
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return new DeviceInfo;
        }

        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        if ($detector->isBot()) {
            return new DeviceInfo(isBot: true);
        }

        return new DeviceInfo(
            isBot: false,
            deviceType: $this->clean($detector->getDeviceName(), self::MAX_TYPE_LENGTH),
            deviceBrand: $this->clean($detector->getBrandName(), self::MAX_NAME_LENGTH),
            deviceModel: $this->clean($detector->getModel(), self::MAX_NAME_LENGTH),
            browser: $this->clean($detector->getClient('name'), self::MAX_NAME_LENGTH),
            browserVersion: $this->clean($detector->getClient('version'), self::MAX_VERSION_LENGTH),
            os: $this->clean($detector->getOs('name'), self::MAX_NAME_LENGTH),
            osVersion: $this->clean($detector->getOs('version'), self::MAX_VERSION_LENGTH),
        );
    }

    /**
     * Keeps a device-detector value, truncated, or nothing.
     *
     * The parameter is `mixed` and not `?string`: `getClient()` and `getOs()`
     * return `array|string|null`, the library answering an array when no
     * precise key is asked for. An array would reach `mb_substr` and die, so it
     * is refused here.
     */
    private function clean(mixed $value, int $limit): ?string
    {
        return (is_string($value) && $value !== '') ? mb_substr($value, 0, $limit) : null;
    }
}
