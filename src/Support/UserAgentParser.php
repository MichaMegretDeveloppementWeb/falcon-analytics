<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use DeviceDetector\DeviceDetector;
use Falcon\Analytics\DTOs\DeviceInfo;

final readonly class UserAgentParser
{
    /** Session column lengths; truncate rather than fail the insert on an exotic UA. */
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
     * Garde une valeur de device-detector, tronquee, ou rien.
     *
     * **Le parametre est `mixed`, et ce n'est pas un relachement.**
     * `getClient()` et `getOs()` rendent `array|string|null` : la bibliotheque
     * rend un tableau quand on ne lui demande pas une cle precise. Le `?string`
     * d'avant promettait donc quelque chose de faux, et un tableau serait
     * arrive jusqu'a `mb_substr` en erreur fatale. Il est rejete ici.
     */
    private function clean(mixed $value, int $limit): ?string
    {
        return (is_string($value) && $value !== '') ? mb_substr($value, 0, $limit) : null;
    }
}
