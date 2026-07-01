<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * One evaluated funnel step: how many visitors reached it (having passed the
 * previous steps in order), the conversion rates, and its weighted score.
 */
final readonly class FunnelStepResult
{
    public function __construct(
        public string $label,
        public float $value,
        public int $visitors,
        public float $conversionFromStart,
        public float $conversionFromPrevious,
        public float $score,
    ) {}
}
