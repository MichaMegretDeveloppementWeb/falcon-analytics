<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

/**
 * The raw campaign/ad identifiers read from a landing URL's query string. Stored
 * verbatim on the session; named and given objectives from the dashboard, matched
 * back to these values at report time.
 */
final readonly class AdTag
{
    public function __construct(
        public ?string $campaign = null,
        public ?string $ad = null,
    ) {}
}
