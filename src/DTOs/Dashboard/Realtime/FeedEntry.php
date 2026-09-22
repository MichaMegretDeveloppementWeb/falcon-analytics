<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Realtime;

/**
 * A line of the live activity feed.
 *
 * @internal
 */
final readonly class FeedEntry
{
    /**
     * @param  string  $action  what happened · « Page vue », the event's label, or the text clicked
     * @param  string|null  $url  the page viewed · null for any other event
     * @param  string  $name  who did it, as the recent visitors name them
     * @param  string  $occurredAt  the time, and its day when it is not today
     */
    public function __construct(
        public int $id,
        public int $sessionId,
        public string $icon,
        public bool $isConversion,
        public string $action,
        public ?string $url,
        public string $name,
        public string $occurredAt,
    ) {}
}
