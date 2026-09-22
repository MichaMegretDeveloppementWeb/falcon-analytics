<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Session;

use Carbon\CarbonImmutable;

/**
 * One step of a session's journey · a page, or an event that came before any
 * page, with the time spent there and what happened on it.
 *
 * @internal
 */
final readonly class JourneyStep
{
    /**
     * @param  int  $barPercent  the share of the longest page, 3 at least · 0 for a step that is not a page
     * @param  list<JourneyEvent>  $children
     */
    public function __construct(
        public bool $isPageview,
        public bool $isConversion,
        public ?string $route,
        public ?string $url,
        public string $label,
        public CarbonImmutable $occurredAt,
        public string $duration,
        public int $barPercent,
        public array $children,
    ) {}
}
