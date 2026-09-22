<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Visitor;

use Carbon\CarbonImmutable;

/**
 * A line of the visitor directory, prepared for it.
 *
 * @internal
 */
final readonly class VisitorRow
{
    /**
     * @param  string  $name  the subject's name, or their label and number, or the visitor's number
     * @param  string|null  $kind  the kind of subject · null for an anonymous visitor
     * @param  string|null  $source  where their first session came from
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $kind,
        public string $uuid,
        public int $sessionCount,
        public CarbonImmutable $firstSeenAt,
        public CarbonImmutable $lastSeenAt,
        public ?string $country,
        public ?string $city,
        public ?string $source,
    ) {}
}
