<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * One evaluated funnel step: how many visitors reached it (having passed the
 * previous steps in order), the conversion rates, and its weighted score.
 *
 * A step declared with parallel branches also carries how many visitors came
 * through each of them, which is the whole point of branching: knowing which
 * way in people actually take.
 */
final readonly class FunnelStepResult
{
    /** @param  array<string, int>  $branches branch label => visitors who came through it */
    public function __construct(
        public string $label,
        public float $value,
        public int $visitors,
        public float $conversionFromStart,
        public float $conversionFromPrevious,
        public float $score,
        public array $branches = [],
    ) {}
}
