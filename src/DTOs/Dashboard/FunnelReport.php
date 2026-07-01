<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * A funnel evaluated over a period: its entrants (visitors who completed the
 * first step), the total weighted score, and the per-step results.
 */
final readonly class FunnelReport
{
    /**
     * @param  list<FunnelStepResult>  $steps
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $entrants,
        public float $totalScore,
        public array $steps,
    ) {}
}
