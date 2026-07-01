<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

/**
 * Resolved acquisition: the classified source plus the raw UTM parameters.
 */
final readonly class Acquisition
{
    public function __construct(
        public ?string $source = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $utmContent = null,
        public ?string $utmTerm = null,
    ) {}
}
