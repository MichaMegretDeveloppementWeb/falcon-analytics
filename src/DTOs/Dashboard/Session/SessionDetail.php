<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Session;

use Carbon\CarbonImmutable;

/**
 * Everything the detail screen of a session shows, prepared for it.
 *
 * @internal
 */
final readonly class SessionDetail
{
    /**
     * @param  list<JourneyStep>  $journey
     * @param  bool  $detailErased  more was recorded than the rows still hold
     * @param  list<TimeShare>  $timeShares  the five longest pages, then the others together
     * @param  string|null  $timeTotal  null when no time was spent on any page
     */
    public function __construct(
        public int $id,
        public CarbonImmutable $startedAt,
        public SessionVisitor $visitor,
        public string $duration,
        public int $pageviewCount,
        public int $clicksCount,
        public int $eventsCount,
        public int $conversionsCount,
        public string $averagePageDuration,
        public array $journey,
        public bool $detailErased,
        public SessionAcquisition $acquisition,
        public array $timeShares,
        public ?string $timeTotal,
        public ?string $country,
        public ?string $city,
        public ?string $ip,
        public string $deviceIcon,
        public ?string $deviceLabel,
        public ?string $browser,
        public ?string $system,
    ) {}
}
