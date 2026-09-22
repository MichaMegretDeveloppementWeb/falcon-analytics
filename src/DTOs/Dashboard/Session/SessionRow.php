<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Session;

use Carbon\CarbonImmutable;

/**
 * A line of the session list, prepared for it.
 *
 * @internal
 */
final readonly class SessionRow
{
    /**
     * @param  string  $name  the subject's name, or their label and number, or the visitor's number
     * @param  string|null  $label  the subject's label, shown beside a name of its own
     * @param  bool  $notConnected  named after the visitor's other sessions, not signed in during this one
     * @param  string|null  $device  the device and browser on one line · null when neither is known
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $label,
        public string $visitorUuid,
        public bool $notConnected,
        public CarbonImmutable $startedAt,
        public string $duration,
        public int $pageviewCount,
        public int $eventsCount,
        public int $conversionsCount,
        public ?string $source,
        public ?string $landingRoute,
        public ?string $landingUrl,
        public ?string $device,
        public ?string $country,
        public ?string $city,
    ) {}
}
