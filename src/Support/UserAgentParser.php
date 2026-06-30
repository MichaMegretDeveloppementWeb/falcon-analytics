<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use DeviceDetector\DeviceDetector;
use Falcon\Analytics\DTOs\DeviceInfo;

final class UserAgentParser
{
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
            deviceType: $this->clean($detector->getDeviceName()),
            deviceBrand: $this->clean($detector->getBrandName()),
            deviceModel: $this->clean($detector->getModel()),
            browser: $this->clean($detector->getClient('name')),
            browserVersion: $this->clean($detector->getClient('version')),
            os: $this->clean($detector->getOs('name')),
            osVersion: $this->clean($detector->getOs('version')),
        );
    }

    private function clean(?string $value): ?string
    {
        return ($value === null || $value === '') ? null : $value;
    }
}
