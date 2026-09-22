<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Visitor;

use Carbon\CarbonImmutable;

/**
 * A line of the sessions of a visitor, on their detail screen.
 *
 * @internal
 */
final readonly class VisitorSessionRow
{
    /**
     * @param  bool  $signedIn  the visitor was signed in during this session
     * @param  string|null  $source  null for a session with no source recorded
     */
    public function __construct(
        public int $id,
        public CarbonImmutable $startedAt,
        public bool $signedIn,
        public string $duration,
        public int $pageviewCount,
        public string $device,
        public ?string $source,
        public ?string $country,
        public ?string $city,
    ) {}
}
