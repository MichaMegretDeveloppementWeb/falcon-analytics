<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Realtime;

/**
 * A visitor of the last day on the realtime board, by their latest session.
 *
 * @internal
 */
final readonly class RecentVisitorRow
{
    /**
     * @param  string  $name  the subject's name, or their label and number, or the visitor's number
     * @param  string  $lastSeen  the time of their last activity, and its day when it is not today
     */
    public function __construct(
        public int $sessionId,
        public string $name,
        public bool $isOnline,
        public string $deviceIcon,
        public string $lastSeen,
        public ?string $city,
    ) {}
}
